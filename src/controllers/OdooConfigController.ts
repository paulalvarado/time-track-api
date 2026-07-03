import type { FastifyRequest, FastifyReply } from "fastify";

export class OdooConfigController {
  static async save(req: FastifyRequest, reply: FastifyReply) {
    reply.status(200).send({ message: "Not implemented yet" });
  }

  static async get(req: FastifyRequest, reply: FastifyReply) {
    reply.status(200).send({ message: "Not implemented yet" });
  }

  static async test(req: FastifyRequest, reply: FastifyReply) {
    reply.status(200).send({ message: "Not implemented yet" });
  }
}
