import { prisma } from "../lib/prisma.js";

export class OdooConfigModel {
  static async findByUserId(userId: string) {
    return prisma.odooConfig.findUnique({ where: { userId } });
  }

  static async upsert(userId: string, data: { url: string; dbName: string; username: string; apiKey: string }) {
    return prisma.odooConfig.upsert({
      where: { userId },
      update: data,
      create: { userId, ...data },
    });
  }

  static async updateGeminiKey(userId: string, geminiApiKey: string) {
    return prisma.odooConfig.update({
      where: { userId },
      data: { geminiApiKey },
    });
  }
}
