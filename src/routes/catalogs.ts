import type { FastifyInstance } from "fastify";
import { CatalogController } from "../controllers/catalogcontroller.js";

export async function catalogRoutes(app: FastifyInstance) {
  app.get("/catalogs", CatalogController.list);
  app.get("/catalogs/:name", CatalogController.getByName);
}
