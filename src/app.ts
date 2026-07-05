import Fastify from "fastify";
import cors from "@fastify/cors";
import jwt from "@fastify/jwt";
import { appConfig } from "./config/app.js";
import { authConfig } from "./config/auth.js";
import { authRoutes } from "./routes/auth.js";
import { odooConfigRoutes } from "./routes/odoo-config.js";
import { projectRoutes } from "./routes/projects.js";
import { syncRoutes } from "./routes/sync.js";
import { taskRoutes } from "./routes/tasks.js";
import { timesheetRoutes } from "./routes/timesheets.js";

export async function buildApp() {
  const app = Fastify({ logger: true });

  await app.register(cors, appConfig.cors);
  await app.register(jwt, { secret: authConfig.jwtSecret });

  await app.register(authRoutes, { prefix: "/api/auth" });
  await app.register(odooConfigRoutes, { prefix: "/api/odoo" });
  await app.register(projectRoutes, { prefix: "/api/projects" });
  await app.register(syncRoutes, { prefix: "/api" });
  await app.register(taskRoutes, { prefix: "/api/projects" });
  await app.register(timesheetRoutes, { prefix: "/api/projects" });

  app.get("/api/health", async () => ({ status: "ok" }));
  return app;
}