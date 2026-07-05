import { prisma } from "../lib/prisma.js";

export class UserModel {
  static async findByEmail(email: string) {
    return prisma.user.findUnique({ where: { email } });
  }

  static async findById(id: string) {
    return prisma.user.findUnique({
      where: { id },
      include: { odooConfig: true },
    });
  }

  static async create(data: { email: string; name: string; password: string }) {
    return prisma.user.create({ data });
  }

  static async updatePassword(id: string, password: string) {
    return prisma.user.update({ where: { id }, data: { password } });
  }

  static async updateName(id: string, name: string) {
    return prisma.user.update({ where: { id }, data: { name } });
  }
}