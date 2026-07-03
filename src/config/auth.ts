export const authConfig = {
  jwtSecret: process.env.JWT_SECRET ?? "dev-secret-change-in-production",
  sessionExpiresIn: "7d",
};
