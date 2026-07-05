import { prisma } from "../lib/prisma.js";

export class ProjectModel {
  static async findByUserId(userId: string) {
    return prisma.project.findMany({ where: { userId }, orderBy: [{ odooUserId: { sort: "asc", nulls: "last" } }, { name: "asc" }] });
  }

  static async upsertMany(userId: string, projects: { odooId: number; name: string; odooUserId?: number | null; color?: number | null }[]) {
    // Delete projects that no longer exist in Odoo
    const odooIds = projects.map((p) => p.odooId);
    await prisma.project.deleteMany({
      where: { userId, odooId: { notIn: odooIds } },
    });

    // Upsert each project
    for (const project of projects) {
      await prisma.project.upsert({
        where: { odooId_userId: { odooId: project.odooId, userId } },
        update: { name: project.name, odooUserId: project.odooUserId, color: project.color },
        create: { odooId: project.odooId, name: project.name, userId, odooUserId: project.odooUserId, color: project.color },
      });
    }

    return this.findByUserId(userId);
  }
}
