import Fastify from "fastify";
import cors from "@fastify/cors";
import { appConfig } from "./config/app.js";
import { authRoutes } from "./routes/auth.js";
import { odooConfigRoutes } from "./routes/odoo-config.js";
import { projectRoutes } from "./routes/projects.js";

export async function buildApp() {
  const app = Fastify({ logger: true });

  await app.register(cors, appConfig.cors);

  // Routes
  await app.register(authRoutes, { prefix: "/api/auth" });
  await app.register(odooConfigRoutes, { prefix: "/api/odoo" });
  await app.register(projectRoutes, { prefix: "/api/projects" });

  // Health check
  app.get("/api/health", async () => ({ status: "ok" }));

  return app;
}
