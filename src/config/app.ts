export const appConfig = {
  port: Number(process.env.PORT ?? 3000),
  host: process.env.HOST ?? "0.0.0.0",
  cors: {
    origin: process.env.CORS_ORIGIN ?? "http://localhost:5173",
    credentials: true,
  },
};
