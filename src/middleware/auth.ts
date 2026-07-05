import type { FastifyRequest, FastifyReply } from "fastify";
import "@fastify/jwt";

export async function authMiddleware(req: FastifyRequest, reply: FastifyReply) {
  try {
    await req.jwtVerify();
  } catch {
    reply.status(401).send({ error: "Unauthorized" });
  }
}
