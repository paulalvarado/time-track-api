import type { FastifyInstance } from "fastify";
import { ProjectController } from "../controllers/ProjectController.js";

export async function projectRoutes(app: FastifyInstance) {
  app.get("/", ProjectController.list);
}
