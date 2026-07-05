import type { FastifyInstance } from "fastify";
import { TaskController } from "../controllers/taskcontroller.js";

export async function taskRoutes(app: FastifyInstance) {
  app.get("/:odooId/tasks", TaskController.listByProject);
}
