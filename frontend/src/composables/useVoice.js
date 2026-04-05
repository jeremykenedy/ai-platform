import { ref } from 'vue'
import api from '@/services/api'

export function useVoice() {
  const isRecording = ref(false)
  const isPlaying = ref(false)
  const transcript = ref('')
  let mediaRecorder = null
  let audioChunks = []

  async function startRecording() {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
    mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/webm;codecs=opus' })
    audioChunks = []

    mediaRecorder.ondataavailable = (e) => {
      if (e.data.size > 0) audioChunks.push(e.data)
    }

    mediaRecorder.onstop = async () => {
      const blob = new Blob(audioChunks, { type: 'audio/webm' })
      stream.getTracks().forEach((t) => t.stop())
      await transcribeAudio(blob)
    }

    mediaRecorder.start()
    isRecording.value = true
  }

  function stopRecording() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
      mediaRecorder.stop()
    }
    isRecording.value = false
  }

  async function transcribeAudio(blob) {
    const formData = new FormData()
    formData.append('audio', blob, 'recording.webm')
    const { data } = await api.post('/audio/transcribe', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    transcript.value = data.text
  }

  async function speak(text) {
    isPlaying.value = true
    try {
      const { data } = await api.post(
        '/audio/tts',
        { text },
        { responseType: 'arraybuffer' }
      )
      const audioContext = new AudioContext()
      const buffer = await audioContext.decodeAudioData(data)
      const source = audioContext.createBufferSource()
      source.buffer = buffer
      source.connect(audioContext.destination)
      source.onended = () => {
        isPlaying.value = false
      }
      source.start()
    } catch {
      isPlaying.value = false
    }
  }

  return { isRecording, isPlaying, transcript, startRecording, stopRecording, speak }
}
