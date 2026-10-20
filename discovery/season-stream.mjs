// Feed the URL's season to the API consumer immediately while discovery runs independently.
export async function* seasonStream(currentId, discoverIds, {limit = 85, onQueued = () => {}} = {}) {
  const queue = [currentId], seen = new Set([currentId]);
  const controller = new AbortController();
  let finished = false, failure, wake;
  const notify = () => { wake?.(); wake = undefined; };
  onQueued(currentId);
  const add = id => {
    if (controller.signal.aborted || seen.has(id) || seen.size >= limit) return;
    seen.add(id); queue.push(id); onQueued(id); notify();
  };
  const producer = Promise.resolve().then(() => discoverIds(add, controller.signal))
    .catch(error => { if (!controller.signal.aborted) failure = error; })
    .finally(() => { finished = true; notify(); });
  try {
    while (queue.length || !finished) {
      if (!queue.length) await new Promise(resolve => { wake = resolve; });
      while (queue.length) yield queue.shift();
    }
    if (failure) throw failure;
  } finally {
    controller.abort();
    await producer;
  }
}
