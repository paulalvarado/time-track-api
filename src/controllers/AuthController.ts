import type { FastifyRequest, FastifyReply } from "fastify";
import { UserModel } from "../models/usermodel.js";
import { authConfig } from "../config/auth.js";
import bcrypt from "bcryptjs";

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
    return reply.status(201).send({ token, user: { id: user.id, email: user.email, name: user.name } });
  }

  static async login(req: FastifyRequest, reply: FastifyReply) {
    const { email, password } = req.body as { email: string; password: string };
    if (!email || !password) return reply.status(400).send({ error: "Email and password are required" });
    const user = await UserModel.findByEmail(email);
    if (!user || !user.password) return reply.status(401).send({ error: "Invalid email or password" });
    const valid = await bcrypt.compare(password, user.password);
    if (!valid) return reply.status(401).send({ error: "Invalid email or password" });
    const token = await reply.jwtSign({ sub: user.id, email: user.email }, { expiresIn: authConfig.sessionExpiresIn });
    return reply.status(200).send({ token, user: { id: user.id, email: user.email, name: user.name } });
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