import xmlrpc from "xmlrpc";

export type OdooProject = {
  id: number;
  name: string;
  user_id?: [number, string];
  color?: number;
};

export class OdooService {
  private url: string;
  private db: string;
  private username: string;
  private apiKey: string;
  private uid: number | null = null;

  constructor(config: { url: string; dbName: string; username: string; apiKey: string }) {
    this.url = config.url.replace(/\/$/, "");
    this.db = config.dbName;
    this.username = config.username;
    this.apiKey = config.apiKey;
  }

  async authenticate(): Promise<number> {
    if (this.uid) return this.uid;

    const commonClient = xmlrpc.createClient({
      url: `${this.url}/xmlrpc/2/common`,
    });

    return new Promise((resolve, reject) => {
      commonClient.methodCall(
        "authenticate",
        [this.db, this.username, this.apiKey, {}],
        (err: Error | null, uid: number) => {
          if (err) return reject(new Error(`Odoo connection error: ${err.message}`));
          if (!uid || uid === 0) return reject(new Error("Odoo authentication failed — invalid credentials"));
          this.uid = uid;
          resolve(uid);
        },
      );
    });
  }

  getOdooUid(): number | null {
    return this.uid;
  }

  async fetchProjects(): Promise<OdooProject[]> {
    return this._searchRead("project.project", ["id", "name", "user_id", "color"]);
  }

  async fetchTasks(projectId: number): Promise<any[]> {
    return this._searchRead("project.task", ["id", "name", "description", "stage_id", "user_ids", "priority", "date_deadline", "color"], [["project_id", "=", projectId]]);
  }

  async fetchStageNames(projectId?: number): Promise<{ id: number; name: string; sequence: number }[]> {
    if (projectId) {
      // First try: stages specifically assigned to this project via project_ids
      const projectStages = await this._searchRead("project.task.type", ["id", "name", "sequence"], [["project_ids", "in", [projectId]]]);
      if (projectStages.length > 0) {
        return projectStages;
      }
    }
    // Fallback: global stages (no specific project assignment)
    return this._searchRead("project.task.type", ["id", "name", "sequence"], [["project_ids", "=", false]]);
  }

  /** Only return stages explicitly assigned to a project (no fallback to global stages) */
  async fetchProjectStageAssignments(projectId: number): Promise<{ id: number; name: string; sequence: number }[]> {
    return this._searchRead("project.task.type", ["id", "name", "sequence"], [["project_ids", "in", [projectId]]]);
  }

  async fetchUserNames(userIds: number[]): Promise<Map<number, string>> {
    if (userIds.length === 0) return new Map();
    const users = await this._searchRead("res.users", ["id", "name", "login"], [["id", "in", userIds]]);
    return new Map(users.map((u: any) => {
      const rawName = u.name || u.login || `User #${u.id}`;
      // If name looks like an email, extract the username part
      const displayName = rawName.includes("@") ? rawName.split("@")[0] : rawName;
      return [u.id, displayName];
    }));
  }

  async fetchTimesheets(taskId: number): Promise<any[]> {
    return this._searchRead("account.analytic.line", ["id", "name", "unit_amount", "date", "employee_id", "user_id"], [["task_id", "=", taskId]]);
  }

  /**
   * Obtiene las opciones de un campo selection de un modelo de Odoo.
   * Útil para catálogos como prioridades, tipos, etc.
   */
  async fetchFieldSelection(model: string, field: string): Promise<{ key: string; value: string }[]> {
    const uid = await this.authenticate();
    return new Promise((resolve, reject) => {
      const objectClient = xmlrpc.createClient({ url: `${this.url}/xmlrpc/2/object` });
      objectClient.methodCall(
        "execute_kw",
        [this.db, uid, this.apiKey, model, "fields_get", [field], { attributes: ["selection", "string", "type"] }],
        (err: Error | null, result: any) => {
          if (err) return reject(new Error(`Odoo fields_get error: ${err.message}`));
          const fieldInfo = result?.[field];
          if (!fieldInfo?.selection) return resolve([]);
          const items: { key: string; value: string }[] = fieldInfo.selection.map(
            ([k, v]: [string, string]) => ({ key: String(k), value: String(v) }),
          );
          resolve(items);
        },
      );
    });
  }

  /**
   * Obtiene el user_id (res.users) a partir de un employee_id (hr.employee).
   * Retorna null si el empleado no tiene usuario vinculado.
   */
  async fetchEmployeeUserId(employeeId: number): Promise<number | null> {
    const uid = await this.authenticate();
    return new Promise((resolve, reject) => {
      const objectClient = xmlrpc.createClient({ url: `${this.url}/xmlrpc/2/object` });
      objectClient.methodCall(
        "execute_kw",
        [this.db, uid, this.apiKey, "hr.employee", "read", [[employeeId], ["user_id"]]],
        (err: Error | null, result: any) => {
          if (err) return reject(new Error(`Odoo read employee error: ${err.message}`));
          if (!result || result.length === 0) return resolve(null);
          const user = result[0]?.user_id;
          if (Array.isArray(user) && user.length >= 2) return resolve(user[0]);
          if (typeof user === "number") return resolve(user);
          resolve(null);
        },
      );
    });
  }

  /**
   * Obtiene empleados de Odoo (hr.employee) con nombre e ID.
   */
  async fetchEmployees(): Promise<{ id: number; name: string; userId?: number }[]> {
    const employees = await this._searchRead("hr.employee", ["id", "name", "user_id"]);
    return employees.map((e: any) => ({
      id: e.id,
      name: e.name || `Employee #${e.id}`,
      userId: Array.isArray(e.user_id) ? e.user_id[0] : (typeof e.user_id === "number" ? e.user_id : undefined),
    }));
  }

  /**
   * Obtiene usuarios de Odoo (res.users) con nombre e información básica.
   */
  async fetchUsers(): Promise<{ id: number; name: string; email: string }[]> {
    const users = await this._searchRead("res.users", ["id", "name", "login"]);
    return users.map((u: any) => ({
      id: u.id,
      name: (u.name || u.login || `User #${u.id}`).includes("@")
        ? (u.name || u.login || `User #${u.id}`).split("@")[0]
        : (u.name || u.login || `User #${u.id}`),
      email: u.login || "",
    }));
  }

  /**
   * Actualiza un registro en Odoo mediante XML-RPC write.
   * Retorna true si la operación fue exitosa.
   */
  async updateRecord(model: string, id: number, values: Record<string, any>): Promise<boolean> {
    const uid = await this.authenticate();
    return new Promise((resolve, reject) => {
      const objectClient = xmlrpc.createClient({ url: `${this.url}/xmlrpc/2/object` });
      objectClient.methodCall(
        "execute_kw",
        [this.db, uid, this.apiKey, model, "write", [[id], values]],
        (err: Error | null, result: boolean) => {
          if (err) return reject(new Error(`Odoo write error: ${err.message}`));
          resolve(result);
        },
      );
    });
  }

  /**
   * Lee un registro específico de Odoo por ID.
   */
  async readRecord(model: string, id: number, fields: string[]): Promise<any> {
    const uid = await this.authenticate();
    return new Promise((resolve, reject) => {
      const objectClient = xmlrpc.createClient({ url: `${this.url}/xmlrpc/2/object` });
      objectClient.methodCall(
        "execute_kw",
        [this.db, uid, this.apiKey, model, "read", [[id], fields]],
        (err: Error | null, result: any[]) => {
          if (err) return reject(new Error(`Odoo read error: ${err.message}`));
          if (!result || result.length === 0) return resolve(null);
          resolve(result[0]);
        },
      );
    });
  }

  /**
   * Crea un registro en Odoo mediante XML-RPC create.
   * Retorna el ID del registro creado.
   */
  async createRecord(model: string, values: Record<string, any>): Promise<number> {
    const uid = await this.authenticate();
    return new Promise((resolve, reject) => {
      const objectClient = xmlrpc.createClient({ url: `${this.url}/xmlrpc/2/object` });
      objectClient.methodCall(
        "execute_kw",
        [this.db, uid, this.apiKey, model, "create", [values]],
        (err: Error | null, result: number) => {
          if (err) return reject(new Error(`Odoo create error: ${err.message}`));
          resolve(result);
        },
      );
    });
  }

  private async _searchRead(model: string, fields: string[], domain: any[] = []): Promise<any[]> {
    const uid = await this.authenticate();
    return new Promise((resolve, reject) => {
      const objectClient = xmlrpc.createClient({ url: `${this.url}/xmlrpc/2/object` });
      objectClient.methodCall(
        "execute_kw",
        [this.db, uid, this.apiKey, model, "search_read", [domain], { fields, limit: 500 }],
        (err: Error | null, result: any[]) => {
          if (err) return reject(new Error(`Odoo fetch error: ${err.message}`));
          resolve(result || []);
        },
      );
    });
  }
}
