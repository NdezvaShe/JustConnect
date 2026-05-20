from __future__ import annotations

import hashlib
import json
import re
from pathlib import Path
from typing import Any, Dict, List

from fastapi import FastAPI
from pydantic import BaseModel

try:
    import spacy
    from spacy.pipeline import EntityRuler
except Exception:  # pragma: no cover
    spacy = None
    EntityRuler = None

try:
    from sentence_transformers import SentenceTransformer
except Exception:  # pragma: no cover
    SentenceTransformer = None


class AnalyseRequest(BaseModel):
    text: str
    filename: str = ""


class EmbedRequest(BaseModel):
    text: str


class LearnRequest(BaseModel):
    example: Dict[str, Any]


app = FastAPI(title="JustConnect Legal NLP Service")

nlp = None
embedder = None
learned_terms: Dict[str, float] = {}
DATA_DIR = Path(__file__).resolve().parent / "data"
LEARNING_DATASET = DATA_DIR / "legal_summary_dataset.jsonl"
LEARNING_INDEX = DATA_DIR / "legal_summary_dataset.index.json"

COURT_PATTERNS = [
    {"label": "COURT_NAME", "pattern": "High Court of Zimbabwe"},
    {"label": "COURT_NAME", "pattern": "Supreme Court of Zimbabwe"},
    {"label": "COURT_NAME", "pattern": "Constitutional Court"},
    {"label": "COURT_NAME", "pattern": "Labour Court"},
]

ORG_PATTERNS = [
    {"label": "ORGANISATION", "pattern": "Judicial Service Commission"},
    {"label": "ORGANISATION", "pattern": "Zimbabwe Republic Police"},
    {"label": "ORGANISATION", "pattern": "Ministry of Justice"},
    {"label": "ORGANISATION", "pattern": "Environmental Management Agency"},
]

LOCATIONS = ["Harare", "Bulawayo", "Mutare", "Gweru", "Masvingo", "Chinhoyi"]


def startup() -> None:
    global nlp, embedder, learned_terms

    if spacy is not None:
        try:
            nlp = spacy.blank("en")
            ruler = nlp.add_pipe("entity_ruler")
            patterns = COURT_PATTERNS + ORG_PATTERNS + [{"label": "LOCATION", "pattern": loc} for loc in LOCATIONS]
            ruler.add_patterns(patterns)
        except Exception:
            nlp = None

    if SentenceTransformer is not None:
        try:
            embedder = SentenceTransformer("all-MiniLM-L6-v2")
        except Exception:
            embedder = None

    learned_terms = _load_learned_terms()


@app.get("/health")
def health() -> Dict[str, Any]:
    return {"status": "ok", "learned_terms": len(learned_terms)}


@app.post("/analyse")
def analyse(payload: AnalyseRequest) -> Dict[str, Dict[str, List[str]]]:
    entities = {
        "COURT_NAME": [],
        "PERSON": [],
        "ORGANISATION": [],
        "LOCATION": [],
        "DATE": [],
        "LEGAL_SECTION": [],
        "CASE_NUMBER": [],
    }

    if nlp is not None:
        doc = nlp(payload.text)
        for ent in doc.ents:
            if ent.label_ in entities:
                entities[ent.label_].append(ent.text.strip())

    for match in re.findall(r"\b(?:Justice|Judge|Advocate)\s+[A-Z][A-Za-z]+(?:\s+[A-Z][A-Za-z]+){0,2}\b", payload.text):
        entities["PERSON"].append(match)
    for match in re.findall(r"\b(?:Section\s+\d+[A-Za-z]?(?:\([^)]+\))?(?:\s+of\s+the\s+[A-Z][A-Za-z\s]+)?|Chapter\s+\d{1,2}:\d{2})\b", payload.text):
        entities["LEGAL_SECTION"].append(match)
    for match in re.findall(r"\b(?:HH|HC|SC|CCZ|LC)\s*\d{1,4}(?:[-/]\d{2,4})?\b", payload.text):
        entities["CASE_NUMBER"].append(match)
    for match in re.findall(r"\b\d{1,2}\s+(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4}\b", payload.text):
        entities["DATE"].append(match)

    cleaned = {label: sorted({item.strip() for item in values if item.strip()})[:15] for label, values in entities.items()}

    return {"entities": cleaned}


@app.post("/embed")
def embed(payload: EmbedRequest) -> Dict[str, List[float]]:
    if embedder is not None:
        vector = embedder.encode(payload.text).tolist()
        return {"vector": vector}

    terms = re.findall(r"[A-Za-z]{3,}", payload.text.lower())
    buckets = [0.0] * 48
    for term in terms:
        buckets[hash(term) % 48] += 1.0
    lower_text = payload.text.lower()
    for term, weight in learned_terms.items():
        if term in lower_text:
            buckets[hash(term) % 48] += min(4.0, max(0.25, weight))

    return {"vector": buckets}


@app.post("/learn")
def learn(payload: LearnRequest) -> Dict[str, Any]:
    global learned_terms
    example = dict(payload.example)
    input_text = str(example.get("input_text") or "").strip()
    target_summary = str(example.get("target_summary") or "").strip()
    if not input_text or not target_summary:
        return {"stored": False, "reason": "missing input_text or target_summary"}

    DATA_DIR.mkdir(parents=True, exist_ok=True)
    example_id = str(example.get("id") or hashlib.sha256((input_text + "\n" + target_summary).encode("utf-8")).hexdigest())
    index = _load_learning_index()
    if example_id in index:
        learned_terms = _merge_learned_terms(learned_terms, _terms_from_example(example))
        return {"stored": False, "reason": "duplicate", "id": example_id, "dataset_size": len(index)}

    example["id"] = example_id
    with LEARNING_DATASET.open("a", encoding="utf-8") as handle:
        handle.write(json.dumps(example, ensure_ascii=False) + "\n")

    index[example_id] = {
        "document_id": example.get("document_id"),
        "summary_id": example.get("summary_id"),
        "summary_type": example.get("summary_type"),
    }
    LEARNING_INDEX.write_text(json.dumps(index, indent=2), encoding="utf-8")
    learned_terms = _merge_learned_terms(learned_terms, _terms_from_example(example))

    return {"stored": True, "id": example_id, "dataset_size": len(index)}


def _load_learning_index() -> Dict[str, Dict[str, Any]]:
    if not LEARNING_INDEX.exists():
        return {}

    try:
        data = json.loads(LEARNING_INDEX.read_text(encoding="utf-8"))
        return data if isinstance(data, dict) else {}
    except Exception:
        return {}


def _load_learned_terms() -> Dict[str, float]:
    if not LEARNING_DATASET.exists():
        return {}

    terms: Dict[str, float] = {}
    try:
        with LEARNING_DATASET.open("r", encoding="utf-8") as handle:
            for line in handle:
                if not line.strip():
                    continue
                example = json.loads(line)
                if isinstance(example, dict):
                    terms = _merge_learned_terms(terms, _terms_from_example(example))
    except Exception:
        return {}

    return dict(sorted(terms.items(), key=lambda item: item[1], reverse=True)[:600])


def _terms_from_example(example: Dict[str, Any]) -> Dict[str, float]:
    labels = example.get("labels") if isinstance(example.get("labels"), dict) else {}
    parts = [
        str(example.get("input_text") or ""),
        str(example.get("target_summary") or ""),
        " ".join(str(item) for item in labels.get("keywords", []) if item),
        " ".join(str(item) for item in labels.get("legal_categories", []) if item),
    ]
    text = "\n".join(parts).lower()
    raw_terms = re.findall(r"[a-z][a-z'-]{3,}(?:\s+[a-z][a-z'-]{3,}){0,2}", text)
    stop = {
        "that", "this", "with", "from", "were", "have", "been", "will", "must",
        "shall", "court", "document", "legal", "summary", "person", "people",
        "case", "matter", "judge", "applicant", "respondent",
    }

    terms: Dict[str, float] = {}
    for term in raw_terms:
        term = re.sub(r"\s+", " ", term).strip()
        if not term or term in stop:
            continue
        terms[term] = terms.get(term, 0.0) + (1.4 if " " in term else 1.0)

    return dict(sorted(terms.items(), key=lambda item: item[1], reverse=True)[:40])


def _merge_learned_terms(left: Dict[str, float], right: Dict[str, float]) -> Dict[str, float]:
    merged = dict(left)
    for term, weight in right.items():
        merged[term] = merged.get(term, 0.0) + float(weight)

    return dict(sorted(merged.items(), key=lambda item: item[1], reverse=True)[:600])


startup()
