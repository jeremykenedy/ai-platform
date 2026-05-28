#!/bin/sh

# Start Ollama in background
ollama serve &
OLLAMA_PID=$!

# Wait for Ollama to be ready
echo "Waiting for Ollama..."
until ollama list >/dev/null 2>&1; do
  sleep 1
done
echo "Ollama ready."

# Pull default models if not present
for model in "llama3.2:latest" "nomic-embed-text:latest"; do
  if ! ollama list | grep -q "$model"; then
    echo "Pulling $model..."
    ollama pull "$model"
  fi
done

# Pre-warm models so the first user request isn't a cold load.
# OLLAMA_KEEP_ALIVE (set in compose) keeps them resident.
echo "Pre-warming llama3.2..."
ollama run llama3.2:latest "hi" >/dev/null 2>&1 || true
echo "Pre-warming nomic-embed-text..."
# embedding model has no chat; trigger via API to load it
curl -sS http://localhost:11434/api/embeddings -d '{"model":"nomic-embed-text:latest","prompt":"warm"}' >/dev/null 2>&1 \
  || wget -qO- --post-data='{"model":"nomic-embed-text:latest","prompt":"warm"}' http://localhost:11434/api/embeddings >/dev/null 2>&1 \
  || true

echo "All models ready."
wait $OLLAMA_PID
