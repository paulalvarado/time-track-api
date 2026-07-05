import type { FastifyInstance } from "fastify";
import { AiController } from "../controllers/aicontroller.js";

export async function aiRoutes(app: FastifyInstance) {
  app.post("/ai/transcribe-timesheet", AiController.transcribeTimesheet);
}
