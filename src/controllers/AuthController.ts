import type { FastifyRequest, FastifyReply } from "fastify";
import { UserModel } from "../models/UserModel.js";
import { authConfig } from "../config/auth.js";
import bcrypt from "bcryptjs";

const IS_PROD = process.env.NODE_ENV === "production";

const COOKIE_OPTIONS = {
  path: "/",
  httpOnly: true,
  secure: IS_PROD,
  sameSite: (IS_PROD ? "none" : "lax") as "none" | "lax",
  maxAge: 7 * 24 * 60 * 60, // 7 días en segundos
};

function setTokenCookie(reply: FastifyReply, token: string) {
  reply.setCookie("token", token, COOKIE_OPTIONS);
}

function clearTokenCookie(reply: FastifyReply) {
  reply.clearCookie("token", { path: "/" });
}

export class AuthController {
  static async register(req: FastifyRequest, reply: FastifyReply) {
    const { email, name, password } = req.body as { email: string; name: string; password: string };
    if (!email || !name || !password) return reply.status(400).send({ error: "Email, name, and password are required" });
    if (password.length < 6) return reply.status(400).send({ error: "Password must be at least 6 characters" });
    const existing = await UserModel.findByEmail(email);
    if (existing) return reply.status(409).send({ error: "Email already registered" });
    const passwordHash = await bcrypt.hash(password, 10);
    const user = await UserModel.create({ email, name, password: passwordHash });
    const token = await reply.jwtSign({ sub: user.id, email: user.email }, { expiresIn: authConfig.sessionExpiresIn });
    setTokenCookie(reply, token);
    return reply.status(201).send({ user: { id: user.id, email: user.email, name: user.name } });
  }

  static async login(req: FastifyRequest, reply: FastifyReply) {
    const { email, password } = req.body as { email: string; password: string };
    if (!email || !password) return reply.status(400).send({ error: "Email and password are required" });
    const user = await UserModel.findByEmail(email);
    if (!user || !user.password) return reply.status(401).send({ error: "Invalid email or password" });
    const valid = await bcrypt.compare(password, user.password);
    if (!valid) return reply.status(401).send({ error: "Invalid email or password" });
    const token = await reply.jwtSign({ sub: user.id, email: user.email }, { expiresIn: authConfig.sessionExpiresIn });
    setTokenCookie(reply, token);
    return reply.status(200).send({ user: { id: user.id, email: user.email, name: user.name } });
  }

  static async logout(_req: FastifyRequest, reply: FastifyReply) {
    clearTokenCookie(reply);
    return reply.status(200).send({ ok: true });
  }

  static async updateProfile(req: FastifyRequest, reply: FastifyReply) {
    try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: "Unauthorized" }); }
    const { sub } = req.user as { sub: string };
    const { name, currentPassword, newPassword } = req.body as {
      name?: string;
      currentPassword?: string;
      newPassword?: string;
    };

    const user = await UserModel.findById(sub);
    if (!user) return reply.status(404).send({ error: "User not found" });

    // Si quiere cambiar contraseña
    if (currentPassword || newPassword) {
      if (!currentPassword || !newPassword) {
        return reply.status(400).send({ error: "Both currentPassword and newPassword are required to change password" });
      }
      if (newPassword.length < 6) {
        return reply.status(400).send({ error: "New password must be at least 6 characters" });
      }
      const valid = await bcrypt.compare(currentPassword, user.password);
      if (!valid) return reply.status(401).send({ error: "Current password is incorrect" });
      const passwordHash = await bcrypt.hash(newPassword, 10);
      await UserModel.updatePassword(sub, passwordHash);
    }

    // Si quiere cambiar nombre
    if (name && name !== user.name) {
      await UserModel.updateName(sub, name);
    }

    const updated = await UserModel.findById(sub);
    return reply.status(200).send({
      user: { id: updated!.id, email: updated!.email, name: updated!.name },
    });
  }

  static async me(req: FastifyRequest, reply: FastifyReply) {
    try { await req.jwtVerify(); } catch { return reply.status(401).send({ error: "Unauthorized" }); }
    const { sub } = req.user as { sub: string };
    const user = await UserModel.findById(sub);
    if (!user) return reply.status(404).send({ error: "User not found" });
    return reply.status(200).send({
      user: {
        id: user.id,
        email: user.email,
        name: user.name,
        hasOdooConfig: !!user.odooConfig,
      },
    });
  }
}