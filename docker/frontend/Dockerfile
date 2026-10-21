FROM node:24-slim

RUN corepack enable

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /app
EXPOSE 3000 24678

ENTRYPOINT ["entrypoint.sh"]
CMD ["pnpm", "dev", "--host", "0.0.0.0"]
