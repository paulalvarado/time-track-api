import { prisma } from "../lib/prisma.js";
import { OdooService } from "../services/odoo.js";

export type OdooTask = {
  id: number;
  name: string;
  stage_id?: [number, string];
  user_ids?: [number, string][];
  priority?: string;
  date_deadline?: string;
  color?: number;
};

export class TaskController {
  static async listByProject(req: any, reply: any) {
    try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: "Unauthorized" }); }
    const { sub } = req.user as { sub: string };
    const { odooId } = req.params as { odooId: string };

    const config = await prisma.odooConfig.findUnique({ where: { userId: sub } });
    if (!config) return reply.status(200).send({ tasks: [], stages: [] });

    try {
      const odoo = new OdooService({
        url: config.url,
        dbName: config.dbName,
        username: config.username,
        apiKey: config.apiKey,
      });

      const odooUid = await odoo.authenticate();

      // Fetch stages for this project (specific stages if set, otherwise global)
      const rawStages = await odoo.fetchStageNames(parseInt(odooId));
      const stages = rawStages
        .sort((a: any, b: any) => a.sequence - b.sequence || a.id - b.id)
        .map((s: any) => ({ id: s.id, name: s.name, sequence: s.sequence }));
      const stageMap = new Map(stages.map((s) => [s.id, s.name]));

      // Fetch tasks
      const rawTasks = await odoo.fetchTasks(parseInt(odooId));

      // Collect unique user IDs from all tasks to fetch their names
      const userIds = new Set<number>();
      rawTasks.forEach((t: any) => {
        const ids = t.user_ids || [];
        ids.forEach((id: any) => { if (typeof id === "number") userIds.add(id); });
      });
      const userNames = await odoo.fetchUserNames([...userIds]);

      const tasks = rawTasks.map((t: any) => {
        const rawAssignees: any[] = t.user_ids || [];
        // Normalize assignees to [id, name] tuples
        const assignees: [number, string][] = rawAssignees.map((a: any) => {
          if (Array.isArray(a) && a.length >= 2) return [a[0], a[1]];
          const id = typeof a === "number" ? a : a[0];
          return [id, userNames.get(id) || `User #${id}`];
        });
        return {
          id: t.id,
          name: t.name,
          description: t.description || "",
          stageId: t.stage_id ? t.stage_id[0] : null,
          stageName: t.stage_id ? stageMap.get(t.stage_id[0]) || t.stage_id[1] : "Uncategorized",
          assignees,
          priority: t.priority || "0",
          deadline: t.date_deadline || null,
          color: t.color ?? null,
          isMyTask: assignees.some((a) => a[0] === odooUid),
        };
      });

      return reply.status(200).send({ tasks, stages });
    } catch (err: any) {
      return reply.status(200).send({ tasks: [], stages: [], error: err.message });
    }
  }
}
