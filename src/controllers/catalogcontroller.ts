import type { FastifyRequest, FastifyReply } from "fastify";
import { prisma } from "../lib/prisma.js";

export class CatalogController {
  /**
   * GET /api/odoo/catalogs/:name
   * Devuelve los items de un catálogo por nombre para el usuario autenticado.
   */
  static async getByName(req: FastifyRequest, reply: FastifyReply) {
    try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: "Unauthorized" }); }
    const { sub } = req.user as { sub: string };
    const { name } = req.params as { name: string };

    // Obtener el OdooConfig del usuario
    const config = await prisma.odooConfig.findUnique({ where: { userId: sub } });
    if (!config) return reply.status(404).send({ error: "Odoo not configured" });

    // Buscar el catálogo
    const catalog = await prisma.catalog.findUnique({
      where: { name_odooConfigId: { name, odooConfigId: config.id } },
      include: { items: { orderBy: { key: "asc" } } },
    });

    if (!catalog) {
      return reply.status(404).send({ error: `Catalog '${name}' not found. Run 'npm run odoo:sync-catalogs' first.` });
    }

    return reply.status(200).send({
      catalog: {
        name: catalog.name,
        lastSyncAt: catalog.lastSyncAt,
        items: catalog.items.map((item: { key: string; value: string; extra: any }) => ({
          key: item.key,
          value: item.value,
          extra: item.extra,
        })),
      },
    });
  }

  /**
   * GET /api/odoo/catalogs
   * Lista todos los catálogos disponibles para el usuario.
   */
  static async list(req: FastifyRequest, reply: FastifyReply) {
    try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: "Unauthorized" }); }
    const { sub } = req.user as { sub: string };

    const config = await prisma.odooConfig.findUnique({ where: { userId: sub } });
    if (!config) return reply.status(404).send({ error: "Odoo not configured" });

    const catalogs = await prisma.catalog.findMany({
      where: { odooConfigId: config.id },
      select: { name: true, lastSyncAt: true },
      orderBy: { name: "asc" },
    });

    return reply.status(200).send({ catalogs });
  }
}
