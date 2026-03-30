<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Support\CrmReferenceData;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $contacts = Contact::query()
            ->when($request->search, fn ($q, $v) =>
                $q->where('name', 'like', "%{$v}%")
                    ->orWhere('phone', 'like', "%{$v}%")
            )
            ->latest()
            ->paginate(50);

        return response()->json($contacts);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'telegram_chat_id' => 'nullable|string|max:255',
            'instagram_id' => 'nullable|string|max:255',
            'source' => ['nullable', 'string', 'max:100', Rule::in(CrmReferenceData::values('lead_sources'))],
            'branch' => $this->branchRules(),
            'notes' => 'nullable|string',
        ]);

        $notes = $data['notes'] ?? null;
        unset($data['notes']);

        $data['meta'] = $this->buildMeta(null, [
            'notes' => $notes,
        ]);

        $contact = $this->findExistingContact(
            phone: $data['phone'] ?? null,
            email: $data['email'] ?? null,
        );

        $wasCreated = false;

        if ($contact) {
            $contact->fill($data);
            $contact->save();
        } else {
            $contact = Contact::create($data);
            $wasCreated = true;
        }

        return response()->json($contact, $wasCreated ? 201 : 200);
    }

    public function show(Contact $contact)
    {
        return response()->json(
            $contact->load(['leads.stage', 'deals.stage'])
        );
    }

    public function update(Request $request, Contact $contact)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'telegram_chat_id' => 'nullable|string|max:255',
            'instagram_id' => 'nullable|string|max:255',
            'source' => ['nullable', 'string', 'max:100', Rule::in(CrmReferenceData::values('lead_sources'))],
            'branch' => $this->branchRules(),
            'notes' => 'nullable|string',
        ]);

        if (array_key_exists('notes', $data)) {
            $data['meta'] = $this->buildMeta($contact->meta, [
                'notes' => $data['notes'],
            ]);
            unset($data['notes']);
        }

        $contact->update($data);

        return response()->json($contact);
    }

    private function branchRules(): array
    {
        $configuredBranches = CrmReferenceData::configuredBranchValues();

        $rules = ['nullable', 'string', 'max:255'];

        if ($configuredBranches !== []) {
            $rules[] = Rule::in($configuredBranches);
        }

        return $rules;
    }

    private function buildMeta(?array $current, array $extra): ?array
    {
        $filtered = array_filter($extra, static fn ($value) => $value !== null && $value !== '' && $value !== []);
        $meta = array_merge($current ?? [], $filtered);

        return $meta === [] ? null : $meta;
    }

    private function findExistingContact(?string $phone, ?string $email): ?Contact
    {
        if ($phone === null && $email === null) {
            return null;
        }

        return Contact::query()
            ->when($phone !== null, fn ($query) => $query->where('phone', $phone))
            ->when($email !== null, fn ($query) => $query->orWhere('email', $email))
            ->first();
    }
}
