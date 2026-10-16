# B15 — AI / Chat mapping

CI3 có hai mặt: widget hỗ trợ khách (`/ai-assistant/*`) và admin (cấu hình AI + hộp thư nhân viên).

Provider thật là **Gemini** (`generativelanguage.googleapis.com`, model mặc định `gemini-3.1-flash-lite`). OpenAI chỉ là nhánh dự phòng khi `ai_provider=openai`. Không streaming, không upload file, không embedding, không queue. PHP tự đọc DB rồi nhét vào prompt. Model không chạy SQL.

API key CI3 đang ghi trong `application/config/ai.php`. Laravel không copy key đó. Key chỉ đọc từ `GEMINI_API_KEY` trong `.env`. Không ghi key ra Blade, JSON, hay log.

| CI3 | Laravel | Rule |
|-----|---------|------|
| `controllers/Ai_assistant.php` | `Storefront\AiAssistantController` | Đăng nhập guard `web`. JSON `login_required` nếu chưa login |
| `services/support/SupportChatService.php` | `App\Services\Ai\ChatService` | AI khi `ai_active`; nhân viên khi `staff_joined`; chờ thì không gọi AI |
| `services/ai/AiAssistantService.php` | cùng `ChatService` | FAQ trước, rồi một lần gọi Gemini |
| `providers/GeminiProvider.php` | `GeminiClient` | Timeout 10s, key ở header, không retry 401/429/5xx |
| `Ai_faq_model` / `Ai_setting_model` | `AiFaq` / `AiSetting` | Bảng cũ. `ai_setting` là MyISAM |
| `ai_conversation` / `ai_message` | model sẵn có | Một user không đọc hội thoại user khác |
| `admin/Ai_assistant.php` | `Admin\AiAssistantController` | `ai.manage` = level 0 và 1 |
| `admin/Support_chat.php` | `Admin\SupportChatController` | `order.chat` = level 0, 1 và 2 |
| `site/chat/widget.php` + `ai-chat.js` | Blade widget, JS cũ | `maxlength` 500 |

Đơn hàng đưa vào prompt chỉ của `Auth::id()`. Không có `cost_price`, nhà cung cấp, hay đơn của người khác.
