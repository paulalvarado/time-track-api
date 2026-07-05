export const geminiConfig = {
  model: process.env.GEMINI_MODEL ?? "gemini-2.5-flash",
  maxAudioSize: 10 * 1024 * 1024, // 10MB
};
