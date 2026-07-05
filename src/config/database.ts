export const databaseConfig = {
  url: process.env.DATABASE_URL ?? "postgresql://timetrack:timetrack_secret@localhost:5432/timetrack",
};
