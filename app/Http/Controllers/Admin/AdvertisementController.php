<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AdvertisementController extends Controller
{
    public function index()
    {
        $advertisements = Advertisement::latest()->paginate(15);
        return view('admin.advertisements.index', compact('advertisements'));
    }

    public function create()
    {
        $advertisement = new Advertisement(['status' => true]);
        return view('admin.advertisements.designer', compact('advertisement'));
    }

    public function edit(Advertisement $advertisement)
    {
        return view('admin.advertisements.designer', compact('advertisement'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['export_path'] = $this->storeExport($request->input('export_image'));
        unset($data['export_image']);
        $advertisement = Advertisement::create($data);
        return redirect()->route('advertisements.edit', $advertisement)->with('status', 'বিজ্ঞাপনটি সংরক্ষণ করা হয়েছে।');
    }

    public function update(Request $request, Advertisement $advertisement)
    {
        $data = $this->validated($request);
        $newPath = $this->storeExport($request->input('export_image'));
        if ($newPath) {
            if ($advertisement->export_path) Storage::disk('public')->delete($advertisement->export_path);
            $data['export_path'] = $newPath;
        }
        unset($data['export_image']);
        $advertisement->update($data);
        return redirect()->route('advertisements.edit', $advertisement)->with('status', 'পরিবর্তন সংরক্ষণ করা হয়েছে।');
    }

    public function destroy(Advertisement $advertisement)
    {
        if ($advertisement->export_path) Storage::disk('public')->delete($advertisement->export_path);
        $advertisement->delete();
        return redirect()->route('advertisements.index')->with('status', 'বিজ্ঞাপন মুছে ফেলা হয়েছে।');
    }

    private function validated(Request $request): array
    {
        $rawDesign = $request->input('design_data');
        if (is_string($rawDesign)) {
            $decoded = json_decode($rawDesign, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                throw ValidationException::withMessages(['design_data' => 'ডিজাইনের তথ্য সঠিক নয়।']);
            }
            $request->merge(['design_data' => $decoded]);
        }
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'placement' => ['required', 'in:header,homepage,sidebar,article'],
            'destination_url' => ['nullable', 'url', 'max:1000'],
            'status' => ['nullable', 'boolean'],
            'design_data' => ['required', 'array'],
            'design_data.width' => ['required', 'integer', 'between:100,2000'],
            'design_data.height' => ['required', 'integer', 'between:100,2000'],
            'design_data.elements' => ['required', 'array', 'max:100'],
            'export_image' => ['nullable', 'string', 'max:10000000'],
        ]);
        $data['status'] = $request->boolean('status');
        return $data;
    }

    private function storeExport(?string $dataUrl): ?string
    {
        if (!$dataUrl) return null;
        if (!preg_match('#^data:image/(png|jpeg);base64,#', $dataUrl, $matches)) {
            throw ValidationException::withMessages(['export_image' => 'শুধু PNG অথবা JPG ছবি গ্রহণযোগ্য।']);
        }
        $encoded = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $binary = base64_decode($encoded, true);
        if ($binary === false || strlen($binary) > 7 * 1024 * 1024) {
            throw ValidationException::withMessages(['export_image' => 'ছবিটি অবৈধ অথবা ৭ MB-এর বেশি।']);
        }
        $extension = $matches[1] === 'jpeg' ? 'jpg' : 'png';
        $path = 'advertisements/'.uniqid('ad_', true).'.'.$extension;
        return Storage::disk('public')->put($path, $binary) ? $path : null;
    }
}
