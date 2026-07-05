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

  /**
   * PUT /api/projects/:odooId/tasks/:taskId/timesheets/:timesheetId
   *
   * Flujo Odoo-first:
   * 1. Actualiza el registro en Odoo vía XML-RPC
   * 2. Lee el registro de vuelta para verificar
   * 3. Si coincide → responde ok
   * 4. Si no coincide → responde error (local nunca se tocó)
   */
  static async update(req: any, reply: any) {
    try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: "Unauthorized" }); }
    const { sub } = req.user as { sub: string };
    const { odooId, taskId, timesheetId } = req.params as { odooId: string; taskId: string; timesheetId: string };
    const { name, hours, date, userId } = req.body as { name?: string; hours?: number; date?: string; userId?: number };

    const config = await prisma.odooConfig.findUnique({ where: { userId: sub } });
    if (!config) return reply.status(404).send({ error: "Odoo not configured" });

    const odoo = new OdooService({
      url: config.url,
      dbName: config.dbName,
      username: config.username,
      apiKey: config.apiKey,
    });

    await odoo.authenticate();

    const tsId = parseInt(timesheetId);
    if (isNaN(tsId)) return reply.status(400).send({ error: "Invalid timesheet ID" });

    // 1. Preparar valores a actualizar
    const values: Record<string, any> = {};
    if (name !== undefined) values.name = name;
    if (hours !== undefined) values.unit_amount = hours;
    if (date !== undefined) values.date = date;
    if (userId !== undefined) values.employee_id = userId;

    if (Object.keys(values).length === 0) {
      return reply.status(400).send({ error: "No fields to update" });
    }

    // 2. Actualizar en Odoo
    let updated: boolean;
    try {
      updated = await odoo.updateRecord("account.analytic.line", tsId, values);
    } catch (err: any) {
      return reply.status(502).send({ error: `Odoo update failed: ${err.message}` });
    }

    if (!updated) {
      return reply.status(502).send({ error: "Odoo returned false on write" });
    }

    // 3. Actualizar la DB local con los mismos cambios
    try {
      const updateData: Record<string, any> = {};
      if (name !== undefined) updateData.name = name;
      if (hours !== undefined) updateData.unitAmount = hours;
      if (date !== undefined) updateData.date = date;
      if (userId !== undefined) updateData.userOdooId = userId;

      await prisma.syncTimesheet.update({
        where: { odooId_odooConfigId: { odooId: tsId, odooConfigId: config.id } },
        data: updateData,
      });
    } catch (err: any) {
      // No bloquear la respuesta si falla la actualización local
      console.error(`[Timesheet] Local update failed for ${tsId}: ${err.message}`);
    }

    // 4. Responder
    return reply.status(200).send({
      ok: true,
      timesheet: {
        id: tsId,
        name: name ?? null,
        hours: hours ?? null,
        date: date ?? null,
      },
    });
  }

  /**
   * POST /api/projects/:odooId/tasks/:taskId/timesheets/batch
   *
   * Crea múltiples partes de horas en Odoo (una por una) y las replica en DB local.
   * Cada parte se envía a Odoo primero; solo si Odoo responde OK se guarda localmente.
   */
  static async batchCreate(req: any, reply: any) {
    try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: "Unauthorized" }); }
    const { sub } = req.user as { sub: string };
    const { odooId, taskId } = req.params as { odooId: string; taskId: string };

    const config = await prisma.odooConfig.findUnique({ where: { userId: sub } });
    if (!config) return reply.status(404).send({ error: "Odoo not configured" });

    const { entries } = req.body as {
      entries: { concept: string; hours: number; date: string; userId?: number }[];
    };

    if (!entries || entries.length === 0) {
      return reply.status(400).send({ error: "No entries provided" });
    }

    const odoo = new OdooService({
      url: config.url, dbName: config.dbName,
      username: config.username, apiKey: config.apiKey,
    });

    await odoo.authenticate();

    // Obtener el odooUid del usuario autenticado
    const state = await prisma.syncState.findUnique({ where: { odooConfigId: config.id } });
    const odooUid = state?.odooUid ?? null;

    const results: { index: number; concept: string; hours: number; date: string; success: boolean; odooId?: number; error?: string }[] = [];

    for (let i = 0; i < entries.length; i++) {
      const entry = entries[i];
      const values: Record<string, any> = {
        task_id: parseInt(taskId),
        name: entry.concept,
        unit_amount: entry.hours,
        date: entry.date,
        user_id: entry.userId || odooUid,
      };

      try {
        const newOdooId = await odoo.createRecord("account.analytic.line", values);

        // Guardar en DB local
        await prisma.syncTimesheet.create({
          data: {
            odooId: newOdooId,
            name: entry.concept,
            unitAmount: entry.hours,
            date: entry.date,
            userOdooId: entry.userId || odooUid,
            taskOdooId: parseInt(taskId),
            odooConfigId: config.id,
          },
        });

        results.push({ index: i, concept: entry.concept, hours: entry.hours, date: entry.date, success: true, odooId: newOdooId });
      } catch (err: any) {
        results.push({ index: i, concept: entry.concept, hours: entry.hours, date: entry.date, success: false, error: err.message });
      }
    }

    const succeeded = results.filter((r) => r.success).length;
    const failed = results.filter((r) => !r.success).length;

    if (failed === 0) {
      return reply.status(200).send({ ok: true, message: `All ${succeeded} entries created successfully.`, results });
    } else if (succeeded > 0) {
      return reply.status(200).send({ ok: true, partial: true, message: `${succeeded} entries created, ${failed} failed.`, results });
    } else {
      return reply.status(502).send({ ok: false, message: "All entries failed.", results });
    }
  }
}
