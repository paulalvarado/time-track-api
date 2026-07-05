import type { FastifyRequest, FastifyReply } from "fastify";
import { ProjectModel } from "../models/ProjectModel.js";
import { OdooConfigModel } from "../models/OdooConfigModel.js";
import { OdooService } from "../services/odoo.js";

export class ProjectController {
  static async list(req: FastifyRequest, reply: FastifyReply) {
    try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: "Unauthorized" }); }
    const { sub } = req.user as { sub: string };

    // Get Odoo config for this user
    const config = await OdooConfigModel.findByUserId(sub);
    if (!config) {
      return reply.status(200).send({ projects: [], odooConnected: false });
    }

    try {
      // Fetch projects from Odoo
      const odoo = new OdooService({
        url: config.url,
        dbName: config.dbName,
        username: config.username,
        apiKey: config.apiKey,
      });

      const odooProjects = await odoo.fetchProjects();
      const odooUid = odoo.getOdooUid();

      // Sync to local database with odooUserId
      const projects = await ProjectModel.upsertMany(
        sub,
        odooProjects.map((p) => ({
          odooId: p.id,
          name: p.name,
          odooUserId: p.user_id ? p.user_id[0] : null,
          color: p.color ?? null,
        })),
      );

      // Add isMine flag and sort: mine first
      const enriched = projects
        .map((p: any) => ({ ...p, isMine: p.odooUserId === odooUid }))
        .sort((a: any, b: any) => {
          if (a.isMine && !b.isMine) return -1;
          if (!a.isMine && b.isMine) return 1;
          return a.name.localeCompare(b.name);
        });

      return reply.status(200).send({ projects: enriched, odooConnected: true });
    } catch (err: any) {
      // If Odoo connection fails, return local projects if any
      const localProjects = await ProjectModel.findByUserId(sub);
      const enriched = localProjects.map((p: any) => ({ ...p, isMine: false }));
      return reply.status(200).send({
        projects: enriched,
        odooConnected: false,
        error: err.message,
      });
    }
  }
}
