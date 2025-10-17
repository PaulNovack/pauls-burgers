# 🎤 Laravel + Faster-Whisper ASR Service

A full-stack demo project combining **Laravel 12** with a separate **Faster-Whisper ASR microservice**.  
It lets you record audio from the browser (via WebRTC), auto-detect silence, upload the audio to Laravel, and get back real-time transcription using [faster-whisper](https://github.com/SYSTRAN/faster-whisper).

Utilizes Piper for speaking to user on burger ordering page and allows you to order burgers by speech.

"Refactor" is latest working branch

![Alt text](BurgerOrder.png?raw=true "Burger Order")

---

## 🚀 Features
- Laravel 12 backend (PHP 8.3, Composer, Nginx, Supervisor)
- Vue 3 frontend with Vite
- REST endpoint: `POST /api/transcribe` for audio uploads
- Separate Python service (FastAPI + Faster-Whisper) for transcription
- Dockerized setup with **docker-compose** (single command to boot PHP app + ASR)
- Logging of timings (model time and end-to-end latency) to Laravel logs

---

## 📦 Requirements
- Docker (24.x or newer)
- Docker Compose plugin
- Git

---

## 🛠️ Setup Instructions

### 1. Clone the repository
```bash
git clone https://github.com/YOUR_USERNAME/laravel-fasterwhisper-asr.git
cd laravel-fasterwhisper-asr
```

### 2. Copy environment files
```bash
cp .env.example .env
```

Make sure the `.env` includes:

```env
APP_NAME=LaravelASR
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

ASR_URL=http://asr:9000
```

### 3. Build and start services
```bash
docker compose build
docker compose up -d
```

This will start:
- **laravel-app** on `http://localhost:8000`
- **asr-service** (FastAPI + Faster-Whisper) on `http://localhost:9000`

### 4. Run migrations
```bash
docker exec -it laravel-app php artisan migrate
```

### 5. Open in browser
Visit:  
👉 [http://localhost:8000](http://localhost:8000)

Record audio, and the transcription should appear when you stop speaking.

!! There are some missing instructions here right now.  You must start the asr-service in the asr-service directory and the piper-service in the piper-service directory for the application to function.

---

## 🔗 API Endpoints

### Health check
```bash
curl http://localhost:9000/health
```

### Transcribe audio
```bash
curl -F "audio=@sample.wav" http://localhost:8000/api/transcribe
```

Response:
```json
{
  "text": "Hello world",
  "time_ms": 312.5,
  "e2e_ms": 452.7
}
```

---

## 📝 Logs & Debugging

Tail Laravel logs:
```bash
docker exec -it laravel-app tail -f storage/logs/laravel.log
```

Tail ASR logs:
```bash
docker logs -f asr-service
```

---


## 🧰 Tech Stack
- PHP 8.3 / Laravel 12
- Vue 3 / Vite / Tailwind
- FastAPI (Python 3.11)
- Faster-Whisper (ASR)
- Docker + Supervisor + Nginx

---

## 📜 License
MIT — free to use and adapt.

---

## 👨‍💻 Author
Developed by **Paul Novack** ([@PaulNovack](https://github.com/PaulNovack))  
Feel free to open issues and PRs!
