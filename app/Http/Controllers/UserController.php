<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDeleteRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Repositories\UnitKerjaRepository;
use App\Services\UserService;
use App\Support\PerPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService,
        private UnitKerjaRepository $unitKerjaRepository,
    ) {
        $this->authorizeResource(User::class, 'pengguna');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $users = $this->userService->list(
            $request->only(['search', 'role']),
            PerPage::resolve($request),
        );
        $roles = Role::orderBy('name')->get();

        return view('pengguna.index', compact('users', 'roles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = new User;
        $unitKerjas = $this->unitKerjaRepository->orderedList();
        $roles = Role::orderBy('name')->get();

        return view('pengguna.form', compact('user', 'unitKerjas', 'roles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $this->userService->store($request->validated());

        return redirect()->route('pengguna.index')->with('success', 'Pengguna berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * Parameter bernama $pengguna (bukan $user) supaya cocok dengan nama
     * parameter route resource "pengguna/{pengguna}" — implicit route model
     * binding Laravel mencocokkan berdasarkan nama, bukan tipe.
     */
    public function edit(User $pengguna)
    {
        $user = $pengguna;
        $unitKerjas = $this->unitKerjaRepository->orderedList();
        $roles = Role::orderBy('name')->get();

        return view('pengguna.form', compact('user', 'unitKerjas', 'roles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $pengguna)
    {
        $this->userService->update($pengguna, $request->validated());

        return redirect()->route('pengguna.index')->with('success', 'Pengguna berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, User $pengguna)
    {
        try {
            $this->userService->destroy($request->user(), $pengguna);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('pengguna.index')->with('success', 'Pengguna berhasil dihapus.');
    }

    public function bulkDestroy(BulkDeleteRequest $request)
    {
        $this->authorize('delete', User::class);

        $users = User::query()->whereKey($request->validated('ids'))->get();
        abort_unless($users->count() === count($request->validated('ids')), 422, 'Sebagian pengguna sudah tidak tersedia.');

        if ($users->contains(fn (User $user) => $user->is($request->user()))) {
            return back()->with('error', 'Pilihan memuat akun Anda sendiri. Batalkan pilihan tersebut sebelum menghapus.');
        }

        DB::transaction(fn () => $users->each(
            fn (User $user) => $this->userService->destroy($request->user(), $user)
        ));

        return redirect()->route('pengguna.index')
            ->with('success', $users->count().' pengguna berhasil dihapus.');
    }
}
