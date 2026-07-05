import type { FastifyRequest, FastifyReply } from "fastify";
import "@fastify/jwt";

export async function authMiddleware(req: FastifyRequest, reply: FastifyReply) {
  try {
    // Si no hay Authorization header, intentar con la cookie
    if (!req.headers.authorization) {
      const token = req.cookies?.token;
      if (token) {
        req.headers.authorization = `Bearer ${token}`;
      }
    }
    await req.jwtVerify();
  } catch {
    reply.status(401).send({ error: "Unauthorized" });
  }
}
