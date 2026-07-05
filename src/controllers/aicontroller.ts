import type { FastifyRequest, FastifyReply } from "fastify";
import { prisma } from "../lib/prisma.js";
import { transcribeTimesheetAudio } from "../services/gemini.js";
import { geminiConfig } from "../config/gemini.js";

export class AiController {
  /**
   * POST /api/ai/transcribe-timesheet
   *
   * Recibe un audio en base64 (JSON body) y lo envía a Gemini para obtener
   * sugerencias de partes de horas estructuradas.
   * La API key de Gemini se obtiene desde la config del usuario en DB.
   */
  static async transcribeTimesheet(req: FastifyRequest, reply: FastifyReply) {
    try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: "Unauthorized" }); }
    const { sub } = req.user as { sub: string };

    const config = await prisma.odooConfig.findUnique({ where: { userId: sub } });
    const apiKey = config?.geminiApiKey || process.env.GEMINI_API_KEY || "";

    if (!apiKey) {
      return reply.status(503).send({ error: "Gemini API key not configured. Set it in Settings." });
    }

    const { audio, mimeType } = req.body as { audio?: string; mimeType?: string };

    if (!audio) {
      return reply.status(400).send({ error: "No audio data provided" });
    }

    const audioBuffer = Buffer.from(audio, "base64");
    if (audioBuffer.length > geminiConfig.maxAudioSize) {
      return reply.status(413).send({ error: "Audio too large. Maximum 10MB." });
    }

    try {
      const entries = await transcribeTimesheetAudio(audio, mimeType || "audio/webm", apiKey);
      return reply.status(200).send({ entries });
    } catch (err: any) {
      return reply.status(502).send({ error: err.message });
    }
  }
}
