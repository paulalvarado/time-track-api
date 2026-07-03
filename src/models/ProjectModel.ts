import { prisma } from "../lib/prisma.js";

export class ProjectModel {
  static async findByUserId(userId: string) {
    return prisma.project.findMany({ where: { userId } });
  }
}
