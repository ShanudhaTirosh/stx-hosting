import { logger } from '../utils/logger.js';

export type TaskHandler = () => Promise<void>;

interface QueuedTask {
  id: string;
  name: string;
  handler: TaskHandler;
  retries: number;
  maxRetries: number;
  backoffMs: number;
}

class TaskQueue {
  private queue: QueuedTask[] = [];
  private isProcessing = false;
  private concurrency = 2; // Process up to 2 tasks concurrently

  public add(name: string, handler: TaskHandler, maxRetries = 3, backoffMs = 2000): void {
    const task: QueuedTask = {
      id: Math.random().toString(36).substring(2, 9),
      name,
      handler,
      retries: 0,
      maxRetries,
      backoffMs,
    };
    this.queue.push(task);
    this.processNext();
  }

  private async processNext(): Promise<void> {
    if (this.isProcessing || this.queue.length === 0) return;
    this.isProcessing = true;

    while (this.queue.length > 0) {
      const task = this.queue.shift();
      if (!task) break;

      try {
        await task.handler();
        logger.debug({ taskId: task.id, taskName: task.name }, 'Queue task completed successfully');
      } catch (err: any) {
        task.retries++;
        if (task.retries <= task.maxRetries) {
          logger.warn(
            { taskId: task.id, taskName: task.name, retry: task.retries, err: err.message },
            'Queue task failed, retrying after backoff'
          );
          await new Promise((res) => setTimeout(res, task.backoffMs * task.retries));
          this.queue.push(task);
        } else {
          logger.error(
            { taskId: task.id, taskName: task.name, err: err.message },
            'Queue task failed permanently after maximum retries'
          );
        }
      }
    }

    this.isProcessing = false;
  }
}

export const taskQueue = new TaskQueue();
