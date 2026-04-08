#!/bin/bash
# Install Ollama
sudo apt-get install zstd -y
curl -fsSL https://ollama.com/install.sh | sh

# Pull llama3.2 model
ollama pull llama3.2

# Verify
curl http://localhost:11434
