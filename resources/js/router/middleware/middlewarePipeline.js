export default function middlewarePipeline (context, middleware, index) {
    const nextMiddleware = middleware[index];

    if (!nextMiddleware) return context.next;

    return (...parameters) => {
        context.next(...parameters);

        const nextPipeline = middlewarePipeline(context, middleware, index + 1);

        nextMiddleware({ ...context, next: nextPipeline });
    };
}
