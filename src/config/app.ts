const corsOrigin = process.env.CORS_ORIGIN ?? "http://localhost:5173";

export const appConfig = {
  port: Number(process.env.PORT ?? 3000),
  host: process.env.HOST ?? "0.0.0.0",
  cors: {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    origin: ((origin: string, callback: any) => {
      if (!origin) return callback(null, corsOrigin);
      const allowed = corsOrigin.split(",").map((o) => o.trim().replace(/^https?:\/\//, ""));
      const domain = origin.replace(/^https?:\/\//, "");
      const match = allowed.find((a) => a === domain);
      // Devolver el origin completo (con protocolo) para que sea un valor CORS válido
      callback(null, match ? origin : false);
    }) as any,
    credentials: true,
  },
};
