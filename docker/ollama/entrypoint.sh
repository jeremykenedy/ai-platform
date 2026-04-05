#!/bin/sh

# Start Ollama in background
ollama serve &
OLLAMA_PID=$!

# Wait for Ollama to be ready
echo "Waiting for Ollama..."
until curl -sf http://localhost:11434/api/tags > /dev/null; do
  sleep 1
done
echo "Ollama ready."

# Pull default models if not present
for model in "llama3.2:latest" "qwen2.5:7b" "mistral:7b"; do
  if ! ollama list | grep -q "$model"; then
    echo "Pulling $model..."
    ollama pull "$model"
  fi
done

echo "All models ready."
wait $OLLAMA_PID
