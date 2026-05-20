# JustConnect Python NLP Microservice

This optional service adds a local `spaCy` and `Sentence Transformers` pipeline for:

- Zimbabwean legal entity recognition
- semantic embeddings for related-case search
- a future path for fine-tuning on local legal corpora

## Run locally

```bash
python -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt
python -m spacy download en_core_web_sm
uvicorn app:app --host 127.0.0.1 --port 8001
```

Then set in Laravel:

```env
PYTHON_NLP_ENABLED=true
PYTHON_NLP_URL=http://127.0.0.1:8001
PYTHON_NLP_TIMEOUT=8
```

Laravel falls back to the existing PHP NLP pipeline when this service is not running.

## Continuous learning dataset

When Laravel completes a summarisation, it writes a source-text/summary training example to:

```text
storage/app/private/nlp_learning/legal_summary_dataset.jsonl
```

If this Python service is enabled, Laravel also posts the same example to `/learn`, which stores it at:

```text
python_nlp_service/data/legal_summary_dataset.jsonl
```

These files are the retraining/fine-tuning dataset for the NLP model. Laravel also updates `storage/app/private/nlp_learning/adaptive_model.json` after every completed summary. The PHP NLP engine reads that adaptive model during later analyses, and the Python service refreshes its in-memory learned terms whenever `/learn` receives a new example.

This gives the application automatic online learning for keyword weighting, category matching, and semantic embedding signals. Full model-weight fine-tuning can still be run later as a separate controlled job when enough examples have accumulated.
