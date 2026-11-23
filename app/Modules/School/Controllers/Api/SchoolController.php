<?php

namespace App\Modules\School\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\School\Models\School;
use App\Modules\School\Requests\SchoolRequest;
use App\Modules\School\Resources\SchoolResource;
use App\Modules\Auth\Services\PermissionService;
use App\Modules\School\Services\SchoolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use App\Modules\School\Controllers\Api\SchoolDeputyController;
use App\Modules\School\Services\SchoolManagerGate;



class SchoolController extends Controller
{
    public function __construct(
        protected SchoolService $schools,
        protected PermissionService $permissions,
        protected SchoolManagerGate $gate
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $paginator = $this->schools->paginateFor(
            $request->user(),
            $request->string('q')->toString() ?: null
        );

        return SchoolResource::collection($paginator);
    }

    public function store(SchoolRequest $request): JsonResponse
    {
        $school = $this->schools->create($request->validated())->loadCount(['classes', 'memberships']);

        return (new SchoolResource($school))->response()->setStatusCode(201);
    }

    public function show(Request $request, School $school): SchoolResource
    {
        $this->assertManages($request, $school);

        return new SchoolResource($school->loadCount(['classes', 'memberships']));
    }
    
    public function update(SchoolRequest $request, School $school): SchoolResource
    {
        $user = $request->user();

        $this->assertManages($request, $school);

        if (! $this->gate->isPrimaryManager($user, $school->id)
            && ! $this->gate->allows($user, $school->id, 'can_edit_school')) {
            abort(403, 'اجازه ویرایش اطلاعات مدرسه را ندارید.');
        }

        $school = $this->schools->update($school, $request->validated());

        return new SchoolResource($school->loadCount(['classes', 'memberships']));
    }

    public function destroy(Request $request, School $school): Response|JsonResponse
    {
        if (! $this->schools->delete($school)) {
            return response()->json([
                'message' => 'این مدرسه کلاس یا عضو دارد و قابل حذف نیست.',
            ], 409);
        }

        return response()->noContent();
    }

    protected function assertManages(Request $request, School $school): void
    {
        $user = $request->user();

        abort_unless($this->permissions->managesSchool($user, $school->id), 403, 'به این مدرسه دسترسی ندارید.');

        // برای عملیات ویرایش (update) محدودیت can_edit_school را هم چک کن
        // (اگر فقط می‌خواهی روی update اعمال شود، می‌توانی شرط را به متد update منتقل کنی)
    }

}
