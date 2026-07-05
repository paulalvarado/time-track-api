import type { FastifyRequest, FastifyReply } from "fastify";
import { OdooConfigModel } from "../models/odooconfigmodel.js";
import { OdooService } from "../services/odoo.js";

export class OdooConfigController {
  static async save(req: FastifyRequest, reply: FastifyReply) {
    try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: "Unauthorized" }); }
    const { sub } = req.user as { sub: string };
    const { url, dbName, username, apiKey } = req.body as {
      url: string;
      dbName: string;
      username: string;
      apiKey: string;
    };
    if (!url || !dbName || !username || !apiKey) {
      return reply.status(400).send({ error: "url, dbName, username, and apiKey are required" });
    }
    const config = await OdooConfigModel.upsert(sub, { url, dbName, username, apiKey });
    return reply.status(200).send({ config: { id: config.id, url: config.url, dbName: config.dbName, username: config.username } });
  }

  static async get(req: FastifyRequest, reply: FastifyReply) {
    try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: "Unauthorized" }); }
    const { sub } = req.user as { sub: string };
    const config = await OdooConfigModel.findByUserId(sub);
    if (!config) return reply.status(404).send({ error: "Odoo not configured" });
    return reply.status(200).send({
      config: {
        id: config.id,
        url: config.url,
        dbName: config.dbName,
        username: config.username,
        hasGeminiKey: !!config.geminiApiKey,
      },
    });
  }

  static async saveGeminiKey(req: FastifyRequest, reply: FastifyReply) {
    try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: "Unauthorized" }); }
    const { sub } = req.user as { sub: string };
    const { geminiApiKey } = req.body as { geminiApiKey: string };

    const config = await OdooConfigModel.findByUserId(sub);
    if (!config) return reply.status(404).send({ error: "Odoo not configured" });

    await OdooConfigModel.updateGeminiKey(sub, geminiApiKey);
    return reply.status(200).send({ ok: true, hasGeminiKey: !!geminiApiKey });
  }

  static async test(req: FastifyRequest, reply: FastifyReply) {
    try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: "Unauthorized" }); }
    const { sub } = req.user as { sub: string };

    const config = await OdooConfigModel.findByUserId(sub);
    if (!config) {
      return reply.status(200).send({ connected: false, error: "Odoo not configured" });
    }

    try {
      const service = new OdooService({
        url: config.url,
        dbName: config.dbName,
        username: config.username,
        apiKey: config.apiKey,
      });
      await service.authenticate();
      return reply.status(200).send({ connected: true });
    } catch (err: any) {
      return reply.status(200).send({ connected: false, error: err.message });
    }
  }
}
