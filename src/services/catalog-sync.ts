import { prisma } from "../lib/prisma.js";
import { OdooService } from "./odoo.js";

type CatalogDefinition = {
  name: string;
  model: string;
  field: string;
};

const CATALOG_DEFINITIONS: CatalogDefinition[] = [
  { name: "priority", model: "project.task", field: "priority" },
  { name: "users", model: "res.users", field: "" }, // especial: usa fetchUsers()
  { name: "employees", model: "hr.employee", field: "" }, // especial: usa fetchEmployees()
];

export class CatalogSyncService {
  /**
   * Sincroniza todos los catálogos definidos para una cuenta de Odoo.
   */
  static async syncCatalogs(odooConfigId: string): Promise<void> {
    const config = await prisma.odooConfig.findUnique({ where: { id: odooConfigId } });
    if (!config) throw new Error("OdooConfig not found");

    const odoo = new OdooService({
      url: config.url,
      dbName: config.dbName,
      username: config.username,
      apiKey: config.apiKey,
    });

    await odoo.authenticate();

    for (const catalogDef of CATALOG_DEFINITIONS) {
      try {
        if (catalogDef.name === "users") {
          await this._syncUserCatalog(odoo, odooConfigId, catalogDef);
        } else if (catalogDef.name === "employees") {
          await this._syncEmployeeCatalog(odoo, odooConfigId, catalogDef);
        } else {
          await this._syncSelectionCatalog(odoo, odooConfigId, catalogDef);
        }
        console.log(`[CatalogSync] ✅ ${catalogDef.name} for ${config.username}`);
      } catch (err: any) {
        console.error(`[CatalogSync] ❌ ${catalogDef.name} for ${config.username}: ${err.message}`);
      }
    }
  }

  /**
   * Sincroniza catálogos basados en campos selection de Odoo.
   */
  private static async _syncSelectionCatalog(
    odoo: OdooService,
    odooConfigId: string,
    def: CatalogDefinition,
  ): Promise<void> {
    const items = await odoo.fetchFieldSelection(def.model, def.field);

    // Obtener o crear el catálogo
    const catalog = await prisma.catalog.upsert({
      where: { name_odooConfigId: { name: def.name, odooConfigId } },
      update: { lastSyncAt: new Date() },
      create: { name: def.name, odooConfigId, lastSyncAt: new Date() },
    });

    // Sincronizar items
    for (const item of items) {
      await prisma.catalogItem.upsert({
        where: { catalogId_key: { catalogId: catalog.id, key: item.key } },
        update: { value: item.value },
        create: { catalogId: catalog.id, key: item.key, value: item.value },
      });
    }

    // Eliminar items que ya no existen en Odoo
    const existingKeys = new Set(items.map((i) => i.key));
    const currentItems = await prisma.catalogItem.findMany({ where: { catalogId: catalog.id } });
    for (const ci of currentItems) {
      if (!existingKeys.has(ci.key)) {
        await prisma.catalogItem.delete({ where: { id: ci.id } });
      }
    }
  }

  /**
   * Sincroniza el catálogo de usuarios desde Odoo.
   */
  private static async _syncUserCatalog(
    odoo: OdooService,
    odooConfigId: string,
    def: CatalogDefinition,
  ): Promise<void> {
    const users = await odoo.fetchUsers();

    const catalog = await prisma.catalog.upsert({
      where: { name_odooConfigId: { name: def.name, odooConfigId } },
      update: { lastSyncAt: new Date() },
      create: { name: def.name, odooConfigId, lastSyncAt: new Date() },
    });

    for (const user of users) {
      const key = String(user.id);
      await prisma.catalogItem.upsert({
        where: { catalogId_key: { catalogId: catalog.id, key } },
        update: { value: user.name, extra: { email: user.email } },
        create: { catalogId: catalog.id, key, value: user.name, extra: { email: user.email } },
      });
    }

    // Limpiar usuarios que ya no existen
    const existingIds = new Set(users.map((u) => String(u.id)));
    const currentItems = await prisma.catalogItem.findMany({ where: { catalogId: catalog.id } });
    for (const ci of currentItems) {
      if (!existingIds.has(ci.key)) {
        await prisma.catalogItem.delete({ where: { id: ci.id } });
      }
    }
  }

  private static async _syncEmployeeCatalog(
    odoo: OdooService, odooConfigId: string, def: CatalogDefinition,
  ): Promise<void> {
    const employees = await odoo.fetchEmployees();
    const catalog = await prisma.catalog.upsert({
      where: { name_odooConfigId: { name: def.name, odooConfigId } },
      update: { lastSyncAt: new Date() },
      create: { name: def.name, odooConfigId, lastSyncAt: new Date() },
    });
    for (const emp of employees) {
      const key = String(emp.id);
      const extra: any = {};
      if (emp.userId) extra.userId = emp.userId;
      await prisma.catalogItem.upsert({
        where: { catalogId_key: { catalogId: catalog.id, key } },
        update: { value: emp.name, extra: Object.keys(extra).length > 0 ? extra : undefined },
        create: { catalogId: catalog.id, key, value: emp.name, extra: Object.keys(extra).length > 0 ? extra : undefined },
      });
    }
    const existingIds = new Set(employees.map((e) => String(e.id)));
    const currentItems = await prisma.catalogItem.findMany({ where: { catalogId: catalog.id } });
    for (const ci of currentItems) {
      if (!existingIds.has(ci.key)) {
        await prisma.catalogItem.delete({ where: { id: ci.id } });
      }
    }
  }
}
