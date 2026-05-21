(function () {
  const WS_BASE = `wss://${location.hostname}:8002/ws/voice`;

  // ── Inject mic button ──────────────────────────────────────────────────────
  const inputBox = document.querySelector(".input-box");
  const micBtn = document.createElement("button");
  micBtn.className = "send-btn";
  micBtn.id = "mic-btn";
  micBtn.title = "Talk to Aria";
  micBtn.style.cssText = "background:transparent;border:1px solid rgba(245,166,35,0.4);margin-right:4px;";
  micBtn.innerHTML = '<span class="icon fill">mic</span>';
  inputBox.insertBefore(micBtn, inputBox.querySelector(".send-btn"));

  // ── State ──────────────────────────────────────────────────────────────────
  let ws = null;
  let recording = false;
  let audioContext = null;
  let currentAudio = null;
  let processor = null;
  let micStream = null;

  // ── Mic button visual states ───────────────────────────────────────────────
  function setMicState(state) {
    const icon = micBtn.querySelector(".icon");
    if (state === "recording") {
      icon.textContent = "mic_off";
      micBtn.style.background = "rgba(248,113,113,0.2)";
      micBtn.style.borderColor = "#f87171";
      icon.style.color = "#f87171";
    } else if (state === "thinking") {
      icon.textContent = "hourglass_top";
      micBtn.style.background = "rgba(245,166,35,0.15)";
      micBtn.style.borderColor = "rgba(245,166,35,0.5)";
      icon.style.color = "#f5a623";
    } else {
      icon.textContent = "mic";
      micBtn.style.background = "transparent";
      micBtn.style.borderColor = "rgba(245,166,35,0.4)";
      icon.style.color = "";
    }
  }

  // ── Audio helpers ──────────────────────────────────────────────────────────
  function float32ToPCM16(float32Array) {
    const buf = new ArrayBuffer(float32Array.length * 2);
    const view = new DataView(buf);
    for (let i = 0; i < float32Array.length; i++) {
      const s = Math.max(-1, Math.min(1, float32Array[i]));
      view.setInt16(i * 2, s < 0 ? s * 0x8000 : s * 0x7fff, true);
    }
    return buf;
  }

  function bufToBase64(buf) {
    let binary = "";
    const bytes = new Uint8Array(buf);
    for (let i = 0; i < bytes.byteLength; i++)
      binary += String.fromCharCode(bytes[i]);
    return btoa(binary);
  }

  // ── WebSocket ──────────────────────────────────────────────────────────────
  function connectWS() {
    if (ws && ws.readyState === WebSocket.OPEN) return;
    ws = new WebSocket(`${WS_BASE}/${sid}`);

    ws.onopen = () => {
      console.log("Voice WS connected");
      ws._ping = setInterval(() => {
        if (ws.readyState === WebSocket.OPEN)
          ws.send(JSON.stringify({ type: "ping" }));
      }, 20000);
    };

    ws.onmessage = (ev) => handleServerMessage(JSON.parse(ev.data));

    ws.onclose = () => {
      clearInterval(ws._ping);
      ws = null;
      setMicState("idle");
      recording = false;
    };

    ws.onerror = () => {
      toast("Voice connection failed", "err");
      setMicState("idle");
    };
  }

  // ── Handle server messages ─────────────────────────────────────────────────
  function handleServerMessage(msg) {
    switch (msg.type) {
      case "transcript":
        addUser("🎤 " + msg.text);
        setMicState("thinking");
        break;

      case "reply_text":
        addAria(markdownToHtml(msg.text));
        break;

      case "audio_url":
        playAudio(`https://${location.hostname}:8002${msg.url}`);
        setMicState("idle");
        break;

      case "action":
        handleVoiceAction(msg.name);
        break;

      case "error":
        console.error("Voice error:", msg.message);
        setMicState("idle");
        break;
    }
  }

  function handleVoiceAction(name) {
    if (name === "cart_updated")        renderCart();
    if (name === "open_address_manager") setTimeout(openAddrManager, 400);
    if (name === "requires_login")       setTimeout(openLogin, 600);
    if (name === "checkout_done")        { cart = []; renderCart(); }
  }

  // ── Play TTS audio ─────────────────────────────────────────────────────────
  async function playAudio(url) {
    if (currentAudio) { currentAudio.pause(); currentAudio = null; }
    const audio = new Audio(url);
    currentAudio = audio;
    audio.play().catch(() => toast("Tap screen to enable audio", "err"));
  }

  // ── Start recording ────────────────────────────────────────────────────────
  async function startRecording() {
    connectWS();
    await new Promise(r => setTimeout(r, 300));

    try {
      micStream = await navigator.mediaDevices.getUserMedia({
        audio: { channelCount: 1, sampleRate: 16000,
                 echoCancellation: true, noiseSuppression: true }
      });
    } catch (e) {
      toast("Microphone permission denied", "err");
      return;
    }

    audioContext = new AudioContext({ sampleRate: 16000 });
    const source = audioContext.createMediaStreamSource(micStream);
    processor = audioContext.createScriptProcessor(4096, 1, 1);

    processor.onaudioprocess = (e) => {
      if (!recording || !ws || ws.readyState !== WebSocket.OPEN) return;
      const pcm = float32ToPCM16(e.inputBuffer.getChannelData(0));
      ws.send(JSON.stringify({ type: "audio", data: bufToBase64(pcm) }));
    };

    source.connect(processor);
    processor.connect(audioContext.destination);
    recording = true;
    setMicState("recording");
    toast("Listening… tap mic to stop 🎤", "ok");
  }

  // ── Stop recording ─────────────────────────────────────────────────────────
  function stopRecording() {
    recording = false;
    if (processor)  { processor.disconnect(); processor = null; }
    if (micStream)  { micStream.getTracks().forEach(t => t.stop()); micStream = null; }
    if (audioContext) { audioContext.close(); audioContext = null; }
    if (ws && ws.readyState === WebSocket.OPEN)
      ws.send(JSON.stringify({ type: "end_utterance" }));
    setMicState("thinking");
  }

  // ── Toggle on mic button click ─────────────────────────────────────────────
  micBtn.addEventListener("click", () => {
    recording ? stopRecording() : startRecording();
  });

  // ── Connect WebSocket on page load ─────────────────────────────────────────
  window.addEventListener("load", () => setTimeout(connectWS, 1000));
})();
