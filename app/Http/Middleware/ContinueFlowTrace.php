<?php

namespace App\Http\Middleware;

use AdelinFeraru\NestedFlowTracker\Core\Enums\SpanStatus;
use AdelinFeraru\NestedFlowTracker\Core\FlowTracker;
use AdelinFeraru\NestedFlowTracker\Core\Span;
use AdelinFeraru\NestedFlowTracker\Core\TraceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wraps each API request in a root span and — unlike the package's built-in
 * auto-http middleware — links that span to the *caller's* span via the inbound
 * W3C `traceparent`. With both services writing to the shared flow store, a
 * cross-service request then renders as a single nested tree
 * (ISSWatch → satellite → wheretheiss.at) in either viewer.
 */
class ContinueFlowTrace
{
    public function __construct(
        private readonly FlowTracker $flow,
    ) {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        if (! $this->flow->enabled()) {
            return $next($request);
        }

        $context = TraceContext::parse($request->headers->get('traceparent'));

        if ($context !== null) {
            $this->flow->setTraceId($context->traceId);
        }

        $route = $request->route();
        $name = $request->method().' '.($route?->uri() ?? $request->path());

        // Attach this service's root span to the upstream caller's span via the
        // option, so parent_span_id is set *before* the row is inserted on open.
        // (nestedflowtracker 3.1's driver only updates the mutable columns on close,
        // so mutating $span->parent_span_id inside the callback would be lost.)
        $options = [
            'root' => true,
            'context' => [
                'method' => $request->method(),
                'path' => $request->path(),
            ],
        ];

        if ($context !== null) {
            $options['parent_span_id'] = $context->parentId;
        }

        return $this->flow->span($name, function (?Span $span) use ($next, $request) {
            $response = $next($request);

            if ($span !== null && $response instanceof Response) {
                $status = $response->getStatusCode();
                $span->context = array_merge($span->context ?? [], ['status' => $status]);

                if ($status >= 500) {
                    $span->status = SpanStatus::Failed;
                }
            }

            return $response;
        }, $options);
    }
}
