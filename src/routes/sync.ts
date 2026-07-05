import type { FastifyInstance } from "fastify";
import { SyncController } from "../controllers/synccontroller.js";

export async function syncRoutes(app: FastifyInstance) {
  app.get("/sync/projects", SyncController.listProjects);
  app.get("/sync/projects/:projectId/stages", SyncController.listStages);
  app.get("/sync/projects/:projectId/tasks", SyncController.listTasks);
  app.get("/sync/projects/:projectId/tasks/:taskId", SyncController.getTask);
  app.get("/sync/projects/:projectId/tasks/:taskId/timesheets", SyncController.listTimesheets);
  app.get("/sync/hours", SyncController.totalHours);
  app.get("/sync/status", SyncController.status);
}
