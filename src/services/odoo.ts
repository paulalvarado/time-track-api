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
