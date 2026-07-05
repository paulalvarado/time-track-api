import type { PgDatabase } from "drizzle-orm/pg-core";
import type { AnyPgTable } from "drizzle-orm/pg-core";
import { getTableConfig } from "drizzle-orm/pg-core";

const TYPE_MAP: Record<string, string> = {
  string: "TEXT",
  number: "INTEGER",
  integer: "INTEGER",
  date: "TIMESTAMP",
  boolean: "BOOLEAN",
  json: "JSONB",
};

export abstract class Migration {
  protected db: PgDatabase<any>;

  constructor(db: PgDatabase<any>) {
    this.db = db;
  }

  protected async createTable(table: AnyPgTable): Promise<void> {
    const cfg = getTableConfig(table);
    const cols = cfg.columns.map((col) => {
      const pgType = TYPE_MAP[col.dataType] || col.dataType.toUpperCase();
      let def = `"${col.name}" ${pgType}`;
      if (col.notNull) def += " NOT NULL";
      if (col.primary) def += " PRIMARY KEY";
      if (col.isUnique) def += " UNIQUE";
      if (col.default !== undefined) {
        if (typeof col.default === "string") {
          def += ` DEFAULT '${col.default}'`;
        } else if (typeof col.default === "number" || typeof col.default === "boolean") {
          def += ` DEFAULT ${col.default}`;
        } else {
          // Drizzle SQL fragment (ej: defaultNow()) → NOW()
          def += " DEFAULT NOW()";
        }
      }
      return def;
    });
    const fks = cfg.foreignKeys
      .map((fk: any) => {
        const col = (fk.columns ?? [])[0];
        const ref = fk.reference;
        if (!ref?.table || !(ref.columns ?? [])[0]) return "";
        return `FOREIGN KEY ("${col.name}") REFERENCES "${(ref.table as any).name}"("${ref.columns[0].name}")`;
      })
      .filter(Boolean);
    const sql = `CREATE TABLE IF NOT EXISTS "${cfg.name}" (${[...cols, ...fks].join(", ")});`;
    await this.db.execute(sql);
  }

  protected async dropTable(name: string): Promise<void> {
    await this.db.execute(`DROP TABLE IF EXISTS "${name}"`);
  }

  protected async execute(sql: string): Promise<void> {
    await this.db.execute(sql);
  }

  abstract run(): Promise<void>;
  abstract down(): Promise<void>;
}
