<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Models\Taxonomy;

final class TaxonomyController extends Controller
{
    public function categories(Request $request): never
    {
        Response::json(['data' => (new Taxonomy())->categories()]);
    }

    public function tags(Request $request): never
    {
        Response::json(['data' => (new Taxonomy())->tags()]);
    }

    public function saveCategory(Request $request, ?string $id = null): never
    {
        Auth::requirePermission('category.manage');
        Csrf::requireValid($request);
        $data = $this->requireValid($request, ['name' => ['required', 'min:2', 'max:120'], 'description' => ['max:2000'], 'sort_order' => ['integer']]);
        $this->action(fn (): array => ['message' => 'Đã lưu chuyên mục.', 'id' => (new Taxonomy())->saveCategory($id === null ? null : (int) $id, $data)]);
    }

    public function deleteCategory(Request $request, string $id): never
    {
        Auth::requirePermission('category.manage');
        Csrf::requireValid($request);
        $this->action(function () use ($id): array {
            (new Taxonomy())->deleteCategory((int) $id);
            return ['message' => 'Đã xóa chuyên mục.'];
        });
    }

    public function saveTag(Request $request, ?string $id = null): never
    {
        Auth::requirePermission('tag.manage');
        Csrf::requireValid($request);
        $data = $this->requireValid($request, ['name' => ['required', 'min:1', 'max:80'], 'description' => ['max:255']]);
        $this->action(fn (): array => ['message' => 'Đã lưu thẻ.', 'id' => (new Taxonomy())->saveTag($id === null ? null : (int) $id, $data)]);
    }

    public function deleteTag(Request $request, string $id): never
    {
        Auth::requirePermission('tag.manage');
        Csrf::requireValid($request);
        $this->action(function () use ($id): array {
            (new Taxonomy())->deleteTag((int) $id);
            return ['message' => 'Đã xóa thẻ.'];
        });
    }

    public function mergeTag(Request $request, string $id): never
    {
        Auth::requirePermission('tag.manage');
        Csrf::requireValid($request);
        $data = $this->requireValid($request, ['target_tag_id' => ['required', 'integer']]);
        $this->action(function () use ($id, $data): array {
            (new Taxonomy())->mergeTag((int) $id, (int) $data['target_tag_id']);
            return ['message' => 'Đã gộp thẻ.'];
        });
    }
}
