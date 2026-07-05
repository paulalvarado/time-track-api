# ============================================================
# STAGE 1: Install ALL dependencies (ignorando scripts no seguros)
# ============================================================
FROM node:22-alpine AS deps
WORKDIR /app

# Copiar solo archivos de dependencias — maximiza cache de capas
COPY package.json ./
COPY prisma/schema.prisma ./prisma/schema.prisma
COPY prisma.config.ts ./

# --ignore-scripts evita ejecutar pre/postinstall automáticamente
RUN npm install --ignore-scripts --legacy-peer-deps

# Solo ejecutamos scripts que conocemos y son seguros
RUN npx prisma generate

# ============================================================
# STAGE 2: Build (TypeScript → JavaScript)
# ============================================================
FROM node:22-alpine AS builder
WORKDIR /app

# Reusar node_modules del stage deps (ya incluye devDeps)
COPY --from=deps /app/node_modules ./node_modules
COPY --from=deps /app/prisma ./prisma

# Copiar código fuente
COPY tsconfig.json ./
COPY src/ ./src/

# Build
RUN npx tsc

# ============================================================
# STAGE 3: Producción — solo runtime
# ============================================================
FROM node:22-alpine AS runner
WORKDIR /app

RUN addgroup --system --gid 1001 nodejs
RUN adduser --system --uid 1001 appuser

# Instalar solo producción (más liviano)
COPY package.json ./
COPY prisma/schema.prisma ./prisma/schema.prisma
COPY prisma.config.ts ./
RUN npm install --ignore-scripts --legacy-peer-deps --omit=dev

# Copiar el cliente de Prisma ya generado desde el stage deps
COPY --from=deps /app/node_modules/@prisma/client ./node_modules/@prisma/client

# Copiar archivos necesarios para migraciones en producción (tsx corre .ts directo)
COPY --from=builder /app/src ./src
COPY migrations/ ./migrations/

# Copiar el build compilado
COPY --from=builder /app/dist ./dist

USER appuser

ENV NODE_ENV=production
ENV PORT=3000
EXPOSE 3000

# Ejecuta migraciones y luego arranca la API
CMD ["npm", "run", "start:migrate"]
