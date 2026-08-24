<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Models\UserNotice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class NoticeController extends ApiController
{
    public function me(Request $request): JsonResponse
    {
        $notices = UserNotice::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', 20));

        return $this->success([
            'items' => $notices->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at->toIso8601String(),
            ])->all(),
            'unread' => UserNotice::query()->where('user_id', $request->user()->id)->whereNull('read_at')->count(),
            'pagination' => [
                'page' => $notices->currentPage(),
                'per_page' => $notices->perPage(),
                'total' => $notices->total(),
            ],
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notice = UserNotice::query()->where('user_id', $request->user()->id)->find($id);

        if (! $notice) {
            return $this->error('Aviso no encontrado', 404);
        }

        $notice->update(['read_at' => now()]);

        return $this->success(['id' => $notice->id, 'read_at' => $notice->refresh()->read_at->toIso8601String()]);
    }
}
