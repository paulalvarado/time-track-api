import type { FastifyRequest, FastifyReply } from "fastify";

export class AuthController {
  static async register(req: FastifyRequest, reply: FastifyReply) {
    reply.status(201).send({ message: "Not implemented yet" });
  }

  static async login(req: FastifyRequest, reply: FastifyReply) {
    reply.status(200).send({ message: "Not implemented yet" });
  }

  static async me(req: FastifyRequest, reply: FastifyReply) {
    reply.status(200).send({ message: "Not implemented yet" });
  }
}
