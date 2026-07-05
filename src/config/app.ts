const corsOrigin = process.env.CORS_ORIGIN ?? "http://localhost:5173";

export const appConfig = {
  port: Number(process.env.PORT ?? 3000),
  host: process.env.HOST ?? "0.0.0.0",
  cors: {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    origin: ((origin: string, callback: any) => {
      if (!origin) return callback(null, corsOrigin);
      const allowed = corsOrigin.split(",").map((o) => o.trim());
      const match = allowed.find(
        (a) => origin === a || origin.endsWith(`://${a}`) || origin.includes(a),
      );
      callback(null, match || corsOrigin);
    }) as any,
    credentials: true,
  },
};
