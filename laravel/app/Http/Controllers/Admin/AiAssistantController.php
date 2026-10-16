<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiFaq;
use App\Models\AiSetting;
use Illuminate\Http\Request;

/**
 * Cấu hình AI và FAQ. Quyền ai.manage nằm ở route.
 */
class AiAssistantController extends Controller
{
    public function settings(Request $request): mixed
    {
        if ($request->isMethod('post') && $request->input('submit')) {
            return $this->saveSettings($request);
        }

        return view('admin.ai.settings', ['settings' => $this->settingView()]);
    }

    public function handlePath(Request $request, string $path = ''): mixed
    {
        $parts = array_values(array_filter(explode('/', trim($path, '/')), fn ($part) => $part !== ''));
        $head = $parts[0] ?? '';

        if ($head === '' || $head === 'settings') {
            return $this->settings($request);
        }
        if ($head === 'conversations') {
            return redirect('/admin/support-chat');
        }
        if ($head !== 'faq') {
            abort(404);
        }

        $action = $parts[1] ?? 'index';
        $id = (int) ($parts[2] ?? 0);

        return match ($action) {
            'index', '' => view('admin.ai.faq', ['list' => AiFaq::query()->orderBy('sort_order')->orderBy('id')->get()]),
            'add' => $this->faqForm($request, 0),
            'edit' => $this->faqForm($request, $id),
            'delete' => $this->faqDelete($request, $id),
            'toggle_active' => $this->faqToggle($request, $id),
            default => abort(404),
        };
    }

    private function saveSettings(Request $request): mixed
    {
        $welcome = mb_substr(trim((string) $request->input('welcome_message', '')), 0, 255);
        $hours = mb_substr(trim((string) $request->input('working_hours_text', '')), 0, 255);
        foreach ([
            'ai_enabled' => $request->input('ai_enabled') === '1' ? '1' : '0',
            'staff_support_enabled' => $request->input('staff_support_enabled') === '1' ? '1' : '0',
            'fallback_to_staff' => $request->input('fallback_to_staff') === '1' ? '1' : '0',
            'welcome_message' => $welcome,
            'working_hours_text' => $hours,
        ] as $key => $value) {
            AiSetting::setValue($key, $value);
        }

        return redirect('/admin/ai-assistant/settings')->with('message_success', 'Đã lưu cấu hình Trợ lý AI.');
    }

    /** @return array<string, mixed> */
    private function settingView(): array
    {
        return [
            'ai_enabled' => AiSetting::isEnabled('ai_enabled', false),
            'staff_support_enabled' => AiSetting::isEnabled('staff_support_enabled', true),
            'fallback_to_staff' => AiSetting::isEnabled('fallback_to_staff', true),
            'welcome_message' => (string) AiSetting::getValue('welcome_message', ''),
            'working_hours_text' => (string) AiSetting::getValue('working_hours_text', ''),
        ];
    }

    private function faqForm(Request $request, int $id): mixed
    {
        $faq = $id > 0 ? AiFaq::query()->find($id) : null;
        if ($id > 0 && ! $faq) {
            return redirect('/admin/ai-assistant/faq')->with('message_fail', 'FAQ không tồn tại.');
        }
        if ($request->isMethod('post') && $request->input('submit')) {
            $question = trim((string) $request->input('question', ''));
            $answer = trim((string) $request->input('answer', ''));
            if ($question === '' || $answer === '') {
                return back()->with('message_fail', 'Vui lòng nhập đủ câu hỏi và câu trả lời.')->withInput();
            }
            $data = [
                'question' => $question,
                'answer' => $answer,
                'keywords' => trim((string) $request->input('keywords', '')),
                'category' => trim((string) $request->input('category', '')),
                'sort_order' => (int) $request->input('sort_order', 0),
                'is_active' => $request->boolean('is_active') ? 1 : 0,
                'updated' => time(),
            ];
            if ($faq) {
                $faq->fill($data)->save();
                $flash = 'Đã cập nhật FAQ.';
            } else {
                $data['created'] = time();
                AiFaq::query()->create($data);
                $flash = 'Đã thêm FAQ.';
            }

            return redirect('/admin/ai-assistant/faq')->with('message_success', $flash);
        }

        return view('admin.ai.faq-form', ['faq' => $faq]);
    }

    private function faqDelete(Request $request, int $id): mixed
    {
        if (! $request->isMethod('post')) {
            abort(404);
        }
        if ($id > 0 && AiFaq::query()->where('id', $id)->delete()) {
            return redirect('/admin/ai-assistant/faq')->with('message_success', 'Đã xóa FAQ.');
        }

        return redirect('/admin/ai-assistant/faq');
    }

    private function faqToggle(Request $request, int $id): mixed
    {
        $faq = AiFaq::query()->find($id);
        if (! $faq) {
            if ($request->ajax()) {
                return response()->json(['ok' => false, 'message' => 'FAQ không tồn tại.']);
            }

            return redirect('/admin/ai-assistant/faq');
        }
        $active = (int) $faq->is_active === 1 ? 0 : 1;
        $faq->is_active = $active;
        $faq->updated = time();
        $faq->save();
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(array_merge([
                'ok' => true,
                'is_active' => $active,
                'label' => $active ? 'Đang bật' : 'Tắt',
            ], \App\Support\Storefront\Csrf::jsonMeta()));
        }

        return redirect('/admin/ai-assistant/faq');
    }
}
