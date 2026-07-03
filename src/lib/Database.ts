import Database from "better-sqlite3";
import { drizzle } from "drizzle-orm/better-sqlite3";
import { databaseConfig } from "../config/database.js";

const sqlite = new Database(databaseConfig.url.replace("file:", ""));
export const db = drizzle(sqlite);
