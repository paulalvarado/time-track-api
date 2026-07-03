import type { FastifyRequest, FastifyReply } from "fastify";

export class ProjectController {
  static async list(req: FastifyRequest, reply: FastifyReply) {
    reply.status(200).send({ message: "Not implemented yet" });
  }
}
