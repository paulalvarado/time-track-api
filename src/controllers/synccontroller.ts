import type { FastifyRequest, FastifyReply } from "fastify";
import { prisma } from "../lib/prisma.js";
import { SyncService } from "../services/sync.js";
import { OdooService } from "../services/odoo.js";

export class SyncController {
  /** GET /api/sync/projects?since=ISO — local-first, fires background sync */
  static async listProjects(req: FastifyRequest, reply: FastifyReply) {
    try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: "Unauthorized" }); }
    const { sub } = req.user as { sub: string };
    const query = req.query as { since?: string };

    const config = await prisma.odooConfig.findUnique({ where: { userId: sub } });
    if (!config) return reply.status(200).send({ projects: [], odooConnected: false });

    let state = await prisma.syncState.findUnique({ where: { odooConfigId: config.id } });
    let odooUid = state?.odooUid ?? null;

    // If we don't have the odooUid yet, do a quick auth to get it (fast, single request)
    if (odooUid === null) {
      try {
        const odoo = new OdooService({
          url: config.url, dbName: config.dbName,
          username: config.username, apiKey: config.apiKey,
        });
        await odoo.authenticate();
        odooUid = odoo.getOdooUid();
        if (odooUid !== null) {
          await prisma.syncState.upsert({
            where: { odooConfigId: config.id },
            update: { odooUid },
            create: { odooConfigId: config.id, odooUid },
          });
          state = await prisma.syncState.findUnique({ where: { odooConfigId: config.id } });
        }
      } catch {}
    }

    // Fire background full sync (fire-and-forget)
    SyncService.syncAll(config.id).catch(() => {});

    // If ?since=ISO, only return changed records since that timestamp
    if (query.since) {
      const sinceDate = new Date(query.since);
      if (!isNaN(sinceDate.getTime())) {
        const changed = await prisma.syncProject.findMany({
          where: {
            odooConfigId: config.id,
            updatedAt: { gt: sinceDate },
          },
          select: { odooId: true, name: true, color: true, odooUserId: true, updatedAt: true },
        });
        return reply.status(200).send({
          changed: changed.map((c) => ({ ...c, isMine: odooUid !== null && c.odooUserId === odooUid })),
          syncing: state?.syncing || false,
          lastSyncAt: state?.lastSyncAt?.toISOString() || null,
        });
      }
    }

    // Full response from local DB, sorted: mine first
    const rawProjects = await prisma.syncProject.findMany({
      where: { odooConfigId: config.id },
      select: { odooId: true, name: true, color: true, odooUserId: true, updatedAt: true },
    });

    const projects = rawProjects
      .map((p) => ({ ...p, isMine: odooUid !== null && p.odooUserId === odooUid }))
      .sort((a, b) => {
        if (a.isMine && !b.isMine) return -1;
        if (!a.isMine && b.isMine) return 1;
        return a.name.localeCompare(b.name);
      });

    return reply.status(200).send({
      projects,
      syncing: state?.syncing || false,
      lastSyncAt: state?.lastSyncAt?.toISOString() || null,
    });
  }

  /** GET /api/sync/projects/:projectId/stages — cached stages for a project */
  static async listStages(req: any, reply: any) {
    try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: "Unauthorized" }); }
    const { sub } = req.user as { sub: string };
    const { projectId } = req.params as { projectId: string };

    const config = await prisma.odooConfig.findUnique({ where: { userId: sub } });
    if (!config) return reply.status(200).send({ stages: [] });

    // Get stages that have tasks in this project
    const stageIds = await prisma.syncTask.findMany({
      where: { odooConfigId: config.id, projectOdooId: parseInt(projectId) },
      select: { stageOdooId: true },
      distinct: ["stageOdooId"],
    });

    const stages = await prisma.syncStage.findMany({
      where: { odooId: { in: stageIds.map((s) => s.stageOdooId!).filter(Boolean) }, odooConfigId: config.id },
      orderBy: [{ sequence: "asc" }, { odooId: "asc" }],
      select: { odooId: true, name: true, sequence: true },
    });

    return reply.status(200).send({ stages });
  }

  /** GET /api/sync/projects/:projectId/tasks — cached tasks for a project */
  static async listTasks(req: any, reply: any) {
    try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: "Unauthorized" }); }
    const { sub } = req.user as { sub: string };
    const { projectId } = req.params as { projectId: string };

    const config = await prisma.odooConfig.findUnique({ where: { userId: sub } });
    if (!config) return reply.status(200).send({ tasks: [], stages: [] });

    const state = await prisma.syncState.findUnique({ where: { odooConfigId: config.id } });

    // Look up project name from SyncProject
    const syncProject = await prisma.syncProject.findUnique({
      where: { odooId_odooConfigId: { odooId: parseInt(projectId), odooConfigId: config.id } },
      select: { name: true },
    });

    // Get stages that are used by tasks in this project
    const usedStageIds = await prisma.syncTask.findMany({
      where: { odooConfigId: config.id, projectOdooId: parseInt(projectId) },
      select: { stageOdooId: true },
      distinct: ["stageOdooId"],
    });
    const projectStageIds = usedStageIds.map((s) => s.stageOdooId!).filter(Boolean);

    // Also include stages explicitly assigned to this project via SyncProjectStage
    // (catches empty stages that belong to the project but have no tasks yet)
    const explicitStages = await prisma.syncProjectStage.findMany({
      where: { odooConfigId: config.id, projectOdooId: parseInt(projectId) },
      select: { stageOdooId: true },
    });
    for (const s of explicitStages) {
      if (!projectStageIds.includes(s.stageOdooId)) {
        projectStageIds.push(s.stageOdooId);
      }
    }

    const stages = await prisma.syncStage.findMany({
      where: { odooId: { in: projectStageIds }, odooConfigId: config.id },
      orderBy: [{ sequence: "asc" }, { odooId: "asc" }],
      select: { odooId: true, name: true },
    });
    const stageMap = new Map(stages.map((s) => [s.odooId, s.name]));

    const rawTasks = await prisma.syncTask.findMany({
      where: { odooConfigId: config.id, projectOdooId: parseInt(projectId) },
      orderBy: { odooId: "desc" },
    });

    const tasks = rawTasks.map((t) => ({
      id: t.odooId,
      name: t.name,
      description: t.description || "",
      stageId: t.stageOdooId,
      stageName: t.stageOdooId ? stageMap.get(t.stageOdooId) || "Uncategorized" : "Uncategorized",
      assignees: (t.assigneeIds as [number, string][]) || [],
      priority: t.priority || "0",
      deadline: t.deadline || null,
      color: t.color ?? null,
    }));

    return reply.status(200).send({
      tasks,
      projectName: syncProject?.name || null,
      stages: stages.map((s) => ({ id: s.odooId, name: s.name })),
      syncing: state?.syncing || false,
      lastSyncAt: state?.lastSyncAt?.toISOString() || null,
    });
  }

  /** GET /api/sync/projects/:projectId/tasks/:taskId/timesheets — cached timesheets */
  static async listTimesheets(req: any, reply: any) {
    try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: "Unauthorized" }); }
    const { sub } = req.user as { sub: string };
    const { taskId } = req.params as { taskId: string };

    const config = await prisma.odooConfig.findUnique({ where: { userId: sub } });
    if (!config) return reply.status(200).send({ timesheets: [] });

    const raw = await prisma.syncTimesheet.findMany({
      where: { odooConfigId: config.id, taskOdooId: parseInt(taskId) },
      orderBy: { date: "desc" },
    });

    // Build user name cache: first from task assignees, then from Odoo
    const userCache = new Map<number, string>();
    const userIds = [...new Set(raw.map((t) => t.userOdooId).filter((id): id is number => id !== null))];
    const nullOdooIds = raw.filter((t) => t.userOdooId === null).map((t) => t.odooId);

    // 1. Look up from task assignees
    const tasks = await prisma.syncTask.findMany({
      where: { odooConfigId: config.id },
      select: { assigneeIds: true },
    });
    for (const task of tasks) {
      const assignees = task.assigneeIds as [number, string][];
      for (const [id, name] of assignees) {
        if (!userCache.has(id)) userCache.set(id, name);
      }
    }

    // 2. Try to fetch names from Odoo (res.users) for known user IDs
    const missingIds = userIds.filter((id) => !userCache.has(id));
    if (missingIds.length > 0) {
      try {
        const odoo = new OdooService({
          url: config.url, dbName: config.dbName,
          username: config.username, apiKey: config.apiKey,
        });
        await odoo.authenticate();
        const userNames = await odoo.fetchUserNames(missingIds);
        userNames.forEach((name, id) => { if (!userCache.has(id)) userCache.set(id, name); });
      } catch (err) {
        console.error("[Timesheets] Failed to fetch user names from Odoo:", err instanceof Error ? err.message : String(err));
      }
    }

    // 3. Build employee name lookup from employees catalog (synced from hr.employee)
    const employeeById = new Map<number, string>(); // employee_id → employee name
    const employeeCatalog = await prisma.catalog.findUnique({
      where: { name_odooConfigId: { name: "employees", odooConfigId: config.id } },
      include: { items: true },
    });
    if (employeeCatalog) {
      for (const item of employeeCatalog.items) {
        employeeById.set(parseInt(item.key), item.value);
      }
    }

    const timesheets = raw.map((t) => {
      let userName = "";
      if (t.userOdooId) {
        // Try employee catalog first (userOdooId may be employee_id or user_id)
        userName = employeeById.get(t.userOdooId) || userCache.get(t.userOdooId) || `User #${t.userOdooId}`;
      }
      return {
        id: t.odooId,
        name: t.name || "",
        hours: t.unitAmount,
        date: t.date || null,
        userName,
        userId: t.userOdooId ?? null,
      };
    });

    return reply.status(200).send({ timesheets });
  }

  /** GET /api/sync/projects/:projectId/tasks/:taskId — single cached task */
  static async getTask(req: any, reply: any) {
    try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: "Unauthorized" }); }
    const { sub } = req.user as { sub: string };
    const { projectId, taskId } = req.params as { projectId: string; taskId: string };

    const config = await prisma.odooConfig.findUnique({ where: { userId: sub } });
    if (!config) return reply.status(200).send({ task: null, projectName: null });

    const [task, syncProject] = await Promise.all([
      prisma.syncTask.findUnique({
        where: { odooId_odooConfigId: { odooId: parseInt(taskId), odooConfigId: config.id } },
      }),
      prisma.syncProject.findUnique({
        where: { odooId_odooConfigId: { odooId: parseInt(projectId), odooConfigId: config.id } },
        select: { name: true },
      }),
    ]);

    if (!task) return reply.status(200).send({ task: null, projectName: syncProject?.name || null });

    let stageName = "Uncategorized";
    if (task.stageOdooId) {
      const stage = await prisma.syncStage.findUnique({
        where: { odooId_odooConfigId: { odooId: task.stageOdooId, odooConfigId: config.id } },
        select: { name: true },
      });
      stageName = stage?.name || "Uncategorized";
    }

    return reply.status(200).send({
      task: {
        id: task.odooId,
        name: task.name,
        description: task.description || "",
        stageId: task.stageOdooId,
        stageName,
        assignees: (task.assigneeIds as [number, string][]) || [],
        priority: task.priority || "0",
        deadline: task.deadline || null,
        color: task.color ?? null,
      },
      projectName: syncProject?.name || null,
    });
  }

  /** GET /api/sync/status — sync status for the current user */
  static async status(req: FastifyRequest, reply: FastifyReply) {
    try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: "Unauthorized" }); }
    const { sub } = req.user as { sub: string };

    const config = await prisma.odooConfig.findUnique({ where: { userId: sub } });
    if (!config) return reply.status(200).send({ configured: false });

    const state = await prisma.syncState.findUnique({ where: { odooConfigId: config.id } });

    return reply.status(200).send({
      configured: true,
      syncing: state?.syncing || false,
      lastSyncAt: state?.lastSyncAt?.toISOString() || null,
      error: state?.error || null,
      odooUid: state?.odooUid ?? null,
    });
  }

  /** GET /api/sync/hours — total hours tracked by the current user */
  static async totalHours(req: FastifyRequest, reply: FastifyReply) {
    try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: "Unauthorized" }); }
    const { sub } = req.user as { sub: string };

    const config = await prisma.odooConfig.findUnique({ where: { userId: sub } });
    if (!config) return reply.status(200).send({ totalHours: 0 });

    const state = await prisma.syncState.findUnique({ where: { odooConfigId: config.id } });
    const odooUid = state?.odooUid ?? null;

    if (odooUid === null) return reply.status(200).send({ totalHours: 0 });

    const result = await prisma.syncTimesheet.aggregate({
      where: { odooConfigId: config.id, userOdooId: odooUid },
      _sum: { unitAmount: true },
    });

    const totalHours = result._sum.unitAmount ?? 0;
    return reply.status(200).send({ totalHours });
  }
}
