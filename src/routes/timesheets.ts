import type { FastifyInstance } from "fastify";
import { TimesheetController } from "../controllers/timesheetcontroller.js";

export async function timesheetRoutes(app: FastifyInstance) {
  app.get("/:odooId/tasks/:taskId/timesheets", TimesheetController.listByTask);
  app.put("/:odooId/tasks/:taskId/timesheets/:timesheetId", TimesheetController.update);
}
