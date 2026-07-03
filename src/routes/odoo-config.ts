import type { FastifyInstance } from "fastify";
import { OdooConfigController } from "../controllers/OdooConfigController.js";

export async function odooConfigRoutes(app: FastifyInstance) {
  app.post("/config", OdooConfigController.save);
  app.get("/config", OdooConfigController.get);
  app.post("/test", OdooConfigController.test);
}
