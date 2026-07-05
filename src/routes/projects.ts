import type { FastifyInstance } from "fastify";
import { ProjectController } from "../controllers/projectcontroller.js";

export async function projectRoutes(app: FastifyInstance) {
  app.get("/", ProjectController.list);
}
