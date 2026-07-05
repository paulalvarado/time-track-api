const corsOrigin = process.env.CORS_ORIGIN ?? "http://localhost:5173";

export const appConfig = {
  port: Number(process.env.PORT ?? 3000),
  host: process.env.HOST ?? "0.0.0.0",
  cors: {
    origin: true,
    credentials: true,
  },
};
