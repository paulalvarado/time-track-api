import { prisma } from "../lib/prisma.js";
import { OdooService } from "../services/odoo.js";

export class TimesheetController {
  static async listByTask(req: any, reply: any) {
    try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: "Unauthorized" }); }
    const { sub } = req.user as { sub: string };
    const { odooId, taskId } = req.params as { odooId: string; taskId: string };

    const config = await prisma.odooConfig.findUnique({ where: { userId: sub } });
    if (!config) return reply.status(200).send({ timesheets: [] });

    try {
      const odoo = new OdooService({
        url: config.url,
        dbName: config.dbName,
        username: config.username,
        apiKey: config.apiKey,
      });

      await odoo.authenticate();

      const raw = await odoo.fetchTimesheets(parseInt(taskId));

      // Collect user IDs to fetch names
      const userIds = new Set<number>();
      raw.forEach((t: any) => { if (t.user_id) userIds.add(typeof t.user_id === "number" ? t.user_id : t.user_id[0]); });
      const userNames = await odoo.fetchUserNames([...userIds]);

      const timesheets = raw.map((t: any) => {
        const userId = typeof t.user_id === "number" ? t.user_id : t.user_id?.[0];
        return {
          id: t.id,
          name: t.name || "",
          hours: t.unit_amount || 0,
          date: t.date || null,
          userName: userId ? userNames.get(userId) || `User #${userId}` : "",
        };
      });

      return reply.status(200).send({ timesheets });
    } catch (err: any) {
      return reply.status(200).send({ timesheets: [], error: err.message });
    }
  }
}
