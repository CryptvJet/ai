# Local Llama Integration Setup Guide

## Overview
This guide will help you set up a fully functional local Llama model that integrates with your existing web chat interface. The system will automatically detect and use the local AI when available, falling back to basic responses when offline.

## Step 1: Install Ollama

### Windows Installation
1. **Download Ollama**: Visit https://ollama.ai/download/windows
2. **Install**: Run the installer (OllamaSetup.exe)
3. **Verify Installation**: 
   ```cmd
   ollama --version
   ```

### Alternative: Manual Installation
If the installer doesn't work:
```cmd
# Download and install manually
curl -fsSL https://ollama.ai/install.sh | sh
```

## Step 2: Install a Llama Model

### Recommended Models (choose one based on your PC specs):

#### For 8GB+ RAM: Llama 3.1 8B
```cmd
ollama pull llama3.1:8b
```

#### For 16GB+ RAM: Llama 3.1 8B Instruct (Recommended)
```cmd
ollama pull llama3.1:8b-instruct-q4_K_M
```

#### For 32GB+ RAM: Llama 3.1 70B (High Performance)
```cmd
ollama pull llama3.1:70b-instruct-q4_K_M
```

#### For Lower-End Systems: Llama 3.2 3B
```cmd
ollama pull llama3.2:3b-instruct-q4_K_M
```

### Verify Model Installation
```cmd
ollama list
```

## Step 3: Start Ollama Server

### Start the Server
```cmd
ollama serve
```
*The server will run on http://localhost:11434*

### Test the Installation
```cmd
# Test with a simple prompt
ollama run llama3.1:8b-instruct-q4_K_M "Hello! Can you introduce yourself?"
```

## Step 4: Configure Your Web Interface

The integration code has been automatically added to your system. The web interface will:

1. **Auto-detect** when Ollama is running
2. **Switch to "Full Power" mode** when local AI is available
3. **Fallback gracefully** to basic responses when offline
4. **Show connection status** in the header

### Connection Status Indicators:
- 🟢 **Full Power Mode**: Local Llama is active
- 🔴 **Chill Mode**: Using basic responses
- ⚪ **Testing Connection**: Checking local AI availability

## Step 5: Usage

### Chat Interface
- Your existing web chat will automatically use local Llama
- No changes needed to your workflow
- Voice features continue to work normally

### PC Bridge Integration
- The Bridge app will detect local AI status
- System monitoring continues as normal
- AI can now analyze PC performance with advanced intelligence

## Advanced Configuration

### Model Selection
To switch models, stop the current one and start another:
```cmd
# Stop current session (Ctrl+C in serve window)
ollama serve

# In another terminal
ollama run llama3.2:3b-instruct-q4_K_M
```

### Memory Optimization
For better performance, you can tune memory usage:
```cmd
# Set memory limit (example: 8GB)
set OLLAMA_HOST=0.0.0.0:11434
set OLLAMA_MAX_LOADED_MODELS=1
ollama serve
```

### Custom System Prompt
The AI will use a special system prompt optimized for your PulseCore data and PC analysis.

## Troubleshooting

### Connection Issues
1. **Check if Ollama is running**: Visit http://localhost:11434
2. **Firewall**: Ensure Windows Firewall allows Ollama
3. **Port conflicts**: Make sure port 11434 is available

### Performance Issues
1. **Insufficient RAM**: Try a smaller model (3B instead of 8B)
2. **Slow responses**: Close other heavy applications
3. **GPU acceleration**: Ensure you have latest drivers

### Model Issues
```cmd
# Remove a model if having issues
ollama rm llama3.1:8b-instruct-q4_K_M

# Re-download
ollama pull llama3.1:8b-instruct-q4_K_M
```

## Security Notes

- Ollama runs locally only - no data leaves your PC
- Models are stored locally in your user directory
- No internet connection required after initial download
- All conversations stay private on your machine

## Performance Expectations

| Model Size | RAM Required | Speed | Quality |
|------------|-------------|-------|---------|
| 3B | 4-6GB | Fast | Good |
| 8B | 8-12GB | Medium | Excellent |
| 70B | 32GB+ | Slower | Outstanding |

## Integration Details

Your web interface now includes:

- **Automatic fallback** between local and basic AI
- **Real-time connection monitoring** 
- **Context-aware responses** using your PulseCore data
- **PC performance analysis** with AI insights
- **Journaling support** with intelligent writing assistance

The system is fully backwards compatible - everything works exactly as before, but now with powerful local AI when available!

## Next Steps

1. Install Ollama using the steps above
2. Choose and download a model
3. Start the Ollama server
4. Visit your web interface - it should automatically detect and switch to "Full Power" mode
5. Enjoy chatting with your local Llama model!

## Updating Models

To update to newer model versions:
```cmd
ollama pull llama3.1:8b-instruct-q4_K_M
```

Ollama will automatically download only the differences, making updates efficient.