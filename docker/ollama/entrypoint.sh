#!/bin/sh

# Start Ollama in background
ollama serve &
OLLAMA_PID=$!

# Wait for Ollama to be ready (ollama CLI ships in the image; curl does not)
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

echo "All models ready."
wait $OLLAMA_PID
