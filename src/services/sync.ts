import { prisma } from "../lib/prisma.js";
import { OdooService } from "./odoo.js";

export class SyncService {
  /**
   * Synchronize all Odoo data for a given OdooConfig into local sync tables.
   * Fetches projects, stages, tasks, and timesheets from Odoo via XML-RPC
   * and upserts them into the corresponding Sync* tables.
   */
  static async syncAll(odooConfigId: string): Promise<void> {
    const config = await prisma.odooConfig.findUnique({ where: { id: odooConfigId } });
    if (!config) throw new Error("OdooConfig not found");

    // Mark syncing
    await prisma.syncState.upsert({
      where: { odooConfigId },
      update: { syncing: true, error: null },
      create: { odooConfigId, syncing: true },
    });

    try {
      const odoo = new OdooService({
        url: config.url,
        dbName: config.dbName,
        username: config.username,
        apiKey: config.apiKey,
      });

      await odoo.authenticate();
      const odooUid = odoo.getOdooUid();

      // 1. Fetch all projects
      const odooProjects = await odoo.fetchProjects();

      // 1.5 Upsert projects into SyncProject
      for (const project of odooProjects) {
        await prisma.syncProject.upsert({
          where: { odooId_odooConfigId: { odooId: project.id, odooConfigId } },
          update: {
            name: project.name,
            color: project.color ?? null,
            odooUserId: project.user_id ? project.user_id[0] : null,
          },
          create: {
            odooId: project.id,
            name: project.name,
            color: project.color ?? null,
            odooUserId: project.user_id ? project.user_id[0] : null,
            odooConfigId,
          },
        });
      }

      // 2. For each project, fetch stages + tasks
      const allStages: Map<number, { id: number; name: string; sequence: number }> = new Map();
      const allTasks: any[] = [];
      const projectStagePairs: Set<string> = new Set();

      for (const project of odooProjects) {
        // Fetch stages for this project
        const stages = await odoo.fetchStageNames(project.id);
        for (const s of stages) {
          if (!allStages.has(s.id)) allStages.set(s.id, s);
          projectStagePairs.add(`${s.id}:${project.id}`);
        }

        // Record explicit stage-project assignments (only stages explicitly assigned to this project)
        const explicitStages = await odoo.fetchProjectStageAssignments(project.id);
        for (const s of explicitStages) {
          projectStagePairs.add(`${s.id}:${project.id}`);
        }

        // Fetch tasks for this project
        const tasks = await odoo.fetchTasks(project.id);
        allTasks.push(...tasks.map((t: any) => ({ ...t, projectOdooId: project.id })));
      }

      // Also fetch global stages (those without project assignment)
      const globalStages = await odoo.fetchStageNames();
      for (const s of globalStages) {
        if (!allStages.has(s.id)) allStages.set(s.id, s);
      }

      // 2.5 Record project-stage relationships in SyncProjectStage
      // Delete old relationships first, then insert current ones
      await prisma.syncProjectStage.deleteMany({
        where: { odooConfigId },
      });
      for (const pair of projectStagePairs) {
        const [stageOdooId, projectOdooId] = pair.split(":").map(Number);
        await prisma.syncProjectStage.create({
          data: { stageOdooId, projectOdooId, odooConfigId },
        }).catch(() => {}); // Ignore duplicates
      }

      // 3. Upsert stages
      for (const stage of allStages.values()) {
        await prisma.syncStage.upsert({
          where: { odooId_odooConfigId: { odooId: stage.id, odooConfigId } },
          update: { name: stage.name, sequence: stage.sequence },
          create: { odooId: stage.id, name: stage.name, sequence: stage.sequence, odooConfigId },
        });
      }

      // 4. Upsert tasks
      // Collect all user IDs from tasks to fetch names
      const allUserIds = new Set<number>();
      for (const task of allTasks) {
        if (task.user_ids) {
          for (const id of task.user_ids) {
            if (typeof id === "number") allUserIds.add(id);
          }
        }
      }
      const userNames = await odoo.fetchUserNames([...allUserIds]);

      for (const task of allTasks) {
        const rawAssignees: any[] = task.user_ids || [];
        const assignees: [number, string][] = rawAssignees.map((a: any) => {
          if (Array.isArray(a) && a.length >= 2) return [a[0], a[1]];
          const id = typeof a === "number" ? a : a[0];
          return [id, userNames.get(id) || `User #${id}`];
        });

        await prisma.syncTask.upsert({
          where: { odooId_odooConfigId: { odooId: task.id, odooConfigId } },
          update: {
            name: task.name,
            description: task.description || "",
            stageOdooId: task.stage_id ? task.stage_id[0] : null,
            assigneeIds: assignees,
            priority: task.priority || "0",
            deadline: task.date_deadline || null,
            color: task.color ?? null,
            projectOdooId: task.projectOdooId,
          },
          create: {
            odooId: task.id,
            name: task.name,
            description: task.description || "",
            stageOdooId: task.stage_id ? task.stage_id[0] : null,
            assigneeIds: assignees,
            priority: task.priority || "0",
            deadline: task.date_deadline || null,
            color: task.color ?? null,
            projectOdooId: task.projectOdooId,
            odooConfigId,
          },
        });
      }

      // 5. Fetch timesheets for all tasks (in batches to avoid too many requests)
      const taskIds = allTasks.map((t) => t.id);
      for (let i = 0; i < taskIds.length; i += 10) {
        const batch = taskIds.slice(i, i + 10);
        await Promise.all(
          batch.map(async (taskId) => {
            try {
              const timesheets = await odoo.fetchTimesheets(taskId);
              for (const ts of timesheets) {
                // Prefer employee_id over user_id
                let userId: number | null = null;
                if (ts.employee_id) {
                  const empId = Array.isArray(ts.employee_id) ? ts.employee_id[0] : ts.employee_id;
                  try {
                    const linkedUserId = await odoo.fetchEmployeeUserId(empId);
                    userId = linkedUserId ?? empId;
                  } catch {
                    userId = empId;
                  }
                }
                // Fallback to user_id only if employee_id is not available
                if (userId === null && ts.user_id) {
                  userId = typeof ts.user_id === "number" ? ts.user_id : ts.user_id?.[0] ?? null;
                }

                await prisma.syncTimesheet.upsert({
                  where: {
                    odooId_odooConfigId: { odooId: ts.id, odooConfigId },
                  },
                  update: {
                    name: ts.name || "",
                    unitAmount: ts.unit_amount || 0,
                    date: ts.date || null,
                    userOdooId: userId,
                    taskOdooId: taskId,
                  },
                  create: {
                    odooId: ts.id,
                    name: ts.name || "",
                    unitAmount: ts.unit_amount || 0,
                    date: ts.date || null,
                    userOdooId: userId,
                    taskOdooId: taskId,
                    odooConfigId,
                  },
                });
              }
            } catch {
              // Skip timesheet fetch errors for individual tasks
            }
          }),
        );
      }

      // 6. Update sync state
      await prisma.syncState.update({
        where: { odooConfigId },
        data: { syncing: false, lastSyncAt: new Date(), error: null, odooUid },
      });
    } catch (err: any) {
      await prisma.syncState.update({
        where: { odooConfigId },
        data: { syncing: false, error: err.message },
      }).catch(() => {});
      throw err;
    }
  }
}
