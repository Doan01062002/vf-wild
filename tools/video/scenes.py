# Kịch bản video hướng dẫn: mỗi "cảnh" = 1 khung hình + 1 đoạn lồng tiếng.
#   say = chữ để đọc (viết theo cách đọc), cap = phụ đề hiển thị (viết đúng chính tả)
#   marks = [selector, số, vị trí]; 'parent:' lấy phần tử cha, 'all:' gộp nhiều phần tử, 'text:' tìm theo chữ
import json
import os

HERE = os.path.dirname(os.path.abspath(__file__))
BASE = 'http://127.0.0.1:8090'
SIDE = lambda p: '#side > a[href="/quan-tri/?p=%s"]' % p

S = []


def add(**k):
    S.append(k)


# ---------------------------------------------------------------- mở đầu
add(id='00-intro', kind='html', html='slides/intro.html', tag='',
    say='Chào bạn. Đây là video hướng dẫn sử dụng trang quản trị website vê ép oai chấm vê en. Chúng ta sẽ đi từ đăng nhập, viết bài, đến tự động viết bài bằng Google Sít.',
    cap='Hướng dẫn sử dụng trang quản trị vfwild.vn: đăng nhập, viết bài và tự động viết bài bằng Google Sheet.')

# ---------------------------------------------------------------- 1. đăng nhập
add(id='01-login', kind='page', path='/quan-tri/', nologin=True, tag='Bước 1 · Đăng nhập',
    marks=[['input[name=username]', 1], ['input[name=password]', 2], ['#login button.btn', 3]],
    say='Bước một, đăng nhập. Mở địa chỉ vê ép oai chấm vê en, gạch chéo, quản trị. Nhập email vào ô số một, mật khẩu vào ô số hai, rồi bấm nút Đăng nhập số ba.',
    cap='Mở vfwild.vn/quan-tri/. Nhập email (1), mật khẩu (2), rồi bấm Đăng nhập (3).')

# ---------------------------------------------------------------- 2. menu
add(id='02a-menu', kind='page', path='/quan-tri/?p=blog', tag='Bước 2 · Menu chính',
    marks=[[SIDE('blog'), 1, 'r'], [SIDE('media'), 2, 'r'], [SIDE('audit'), 3, 'r']],
    say='Sau khi đăng nhập, bạn thấy menu chính ở bên trái. Số một là Bài viết, để viết và sửa bài. Số hai là Thư viện ảnh. Số ba là Kiểm tra nội dung, chấm điểm từng bài.',
    cap='Menu bên trái: Bài viết (1), Thư viện ảnh (2), Kiểm tra nội dung (3).')
add(id='02b-menu', kind='page', path='/quan-tri/?p=blog', tag='Bước 2 · Menu chính',
    marks=[[SIDE('review'), 4, 'r'], [SIDE('auto'), 5, 'r'], [SIDE('users'), 6, 'r'], [SIDE('tracking'), 7, 'r']],
    say='Số bốn là Review và học, dành cho nhân viên duyệt bài do trí tuệ nhân tạo viết. Số năm là Tự động viết bài. Số sáu là Người dùng, và số bảy là Mã đo lường.',
    cap='Review và học (4), Tự động viết bài (5), Người dùng (6), Mã đo lường (7).')

# ---------------------------------------------------------------- 3. danh sách bài
add(id='03a-list', kind='page', path='/quan-tri/?p=blog', tag='Bước 3 · Danh sách bài viết',
    marks=[['ul.subsubsub', 1], ['.listbar form', 2, 'r'], ['.pagehead a.btn', 3, 'r']],
    say='Trang Bài viết liệt kê toàn bộ bài. Số một là các bộ lọc: Tất cả, Đã đăng và Bản nháp. Số hai là ô lọc theo chuyên mục và ô tìm kiếm. Số ba là nút Viết bài mới.',
    cap='Bộ lọc trạng thái (1), lọc chuyên mục và tìm kiếm (2), nút Viết bài mới (3).')
add(id='03b-list', kind='page', path='/quan-tri/?p=blog', tag='Bước 3 · Danh sách bài viết',
    marks=[['table th:nth-child(5)', 1], ['table th:nth-child(6)', 2, 'r'], ['text:Sửa', 3]],
    say='Cột SEO cho biết điểm của bài, số hai là ngày đăng hoặc trạng thái nháp. Dưới mỗi tiêu đề là các lệnh số ba: Sửa, Xem, Về nháp hoặc Đăng, và Thùng rác.',
    cap='Cột SEO (1), ngày đăng / nháp (2), lệnh Sửa – Xem – Về nháp – Thùng rác (3).')

# ---------------------------------------------------------------- 4. trình soạn
EDIT = '/quan-tri/?p=blog&edit=chi-phi-van-hanh-vf-wild'
add(id='04a-edit', kind='page', path=EDIT, tag='Bước 4 · Viết và sửa bài',
    marks=[['#f-title', 1], ['parent:#slug-text', 2]],
    say='Bấm Viết bài mới hoặc Sửa để mở trình soạn. Nhập tiêu đề ở ô số một. Đường dẫn bài tự sinh bên dưới, số hai. Chỉ đổi khi thật cần, vì đổi đường dẫn thì link cũ không còn dùng được.',
    cap='Tiêu đề bài (1). Đường dẫn tự sinh (2) – đổi đường dẫn sẽ làm link cũ hỏng.')
add(id='04b-edit', kind='page', path=EDIT, tag='Bước 4 · Viết và sửa bài',
    marks=[['#rte-bar', 3], ['#add-media', 4], ['[data-act=video]', 5]],
    say='Thanh công cụ số ba có kiểu đoạn, in đậm, in nghiêng, danh sách, chèn liên kết và chèn bảng. Nút Thêm ảnh số bốn chèn ảnh vào bài. Nút Video số năm đặt video tại vị trí con trỏ.',
    cap='Thanh công cụ (3), Thêm ảnh (4), nút ▶ Video đặt video tại vị trí con trỏ (5).')
add(id='04c-edit', kind='page', path=EDIT, tag='Bước 4 · Viết và sửa bài',
    marks=[['#f-date', 1], ['#draft-btn', 2], ['#preview-btn', 3, 'r'], ['#save-btn', 4]],
    say='Cột bên phải, hộp Đăng. Số một là ngày đăng: chọn ngày trong tương lai để hẹn lịch. Số hai là Chuyển về nháp, số ba là Xem trước, và số bốn là nút Cập nhật hoặc Đăng để lưu bài.',
    cap='Ngày đăng – chọn ngày tương lai để hẹn lịch (1), Chuyển về nháp (2), Xem trước (3), Cập nhật / Đăng (4).')
add(id='04d-edit', kind='page', path=EDIT, tag='Bước 4 · Viết và sửa bài', scroll='#f-tags', scrollBy=-90,
    marks=[['#f-tags', 1], ['#cover-prev', 2]],
    say='Kéo xuống để nhập thẻ, số một, và đặt ảnh bìa, số hai. Bấm Đổi ảnh đại diện để lấy từ thư viện hoặc tải ảnh mới.',
    cap='Thẻ của bài (1) và ảnh bìa (2): bấm Đổi ảnh đại diện để chọn từ thư viện hoặc tải ảnh mới.')
add(id='04e-edit', kind='page', path=EDIT, tag='Bước 4 · Viết và sửa bài', scroll='#f-alt', scrollBy=-150,
    marks=[['#f-alt', 1], ['#f-ctitle', 2], ['#f-video', 3]],
    say='Với ảnh bìa, điền mô tả ảnh vào ô alt, số một. Ô tiêu đề ảnh, số hai, tạo chú thích dưới ảnh nếu bạn muốn. Ô Link video, số ba, nhận link YouTube, Vimeo hoặc tệp em pê bốn.',
    cap='Mô tả ảnh – alt (1), tiêu đề ảnh (2), link video YouTube / Vimeo / .mp4 (3).')
add(id='04f-edit', kind='page', path=EDIT, tag='Bước 4 · Viết và sửa bài', scroll='#f-seo', scrollBy=-150,
    marks=[['#f-seo', 1], ['#f-desc', 2]],
    say='Tiêu đề S E O, số một, và mô tả S E O, số hai, quyết định cách bài hiện trên Google. Mỗi ô có bộ đếm ký tự để bạn viết vừa đủ, không quá dài.',
    cap='Tiêu đề SEO (1) và mô tả SEO (2) quyết định cách bài hiện trên Google. Có bộ đếm ký tự.')
add(id='04g-edit', kind='page', path=EDIT, tag='Bước 4 · Viết và sửa bài', scroll='#score', scrollBy=-150,
    marks=[['#score', 1], ['#checks', 2]],
    say='Hộp chấm điểm, số một, cho biết điểm S E O của bài. Danh sách số hai cho biết bài còn thiếu gì, ví dụ từ khóa, độ dài và liên kết. Hãy đạt điểm tốt trước khi đăng.',
    cap='Điểm SEO (1) và danh sách việc còn thiếu (2). Nên đạt điểm tốt trước khi đăng.')

# ---------------------------------------------------------------- 5. thư viện ảnh
add(id='05-media', kind='page', path='/quan-tri/?p=media', tag='Bước 5 · Thư viện ảnh',
    marks=[['text:Thêm ảnh mới', 1, 'r'], ['text:Xóa', 2]],
    say='Thư viện ảnh chứa ảnh có sẵn của trang và ảnh bạn tải lên. Bấm Thêm ảnh mới, số một, để tải ảnh. Hệ thống tự nén và tạo nhiều cỡ. Chỉ ảnh chưa dùng trong bài nào mới xóa được, số hai.',
    cap='Thêm ảnh mới (1). Chỉ xóa được ảnh đã tải lên và chưa dùng trong bài nào (2).')

# ---------------------------------------------------------------- 6. chuyên mục
add(id='06-cats', kind='page', path='/quan-tri/?p=cats', tag='Bước 6 · Chuyên mục',
    marks=[['input[placeholder^="Ví dụ"]', 1], ['text:Đổi tên', 2], ['text:Xóa', 3]],
    say='Chuyên mục dùng để phân nhóm bài. Nhập tên vào ô số một rồi bấm Thêm chuyên mục. Mỗi chuyên mục có thể Đổi tên, số hai. Chuyên mục chưa có bài mới được Xóa, số ba.',
    cap='Thêm chuyên mục (1), Đổi tên (2), Xóa chuyên mục chưa có bài (3).')

# ---------------------------------------------------------------- 7. tự động viết bài
add(id='07a-auto', kind='page', path='/quan-tri/?p=auto', tag='Bước 7 · Tự động viết bài', wait=3500,
    marks=[['text:Tắt tự động', 1], ['text:Chạy ngay 1 bài', 2, 'r']],
    say='Tự động viết bài là nơi điều khiển máy viết bài. Trên cùng cho biết đang bật hay tắt. Số một là nút Bật hoặc Tắt tự động. Số hai là Chạy ngay một bài, để thử ngay không cần chờ lịch.',
    cap='Nút Bật / Tắt tự động (1). Chạy ngay 1 bài để thử (2).')
add(id='07b-auto', kind='page', path='/quan-tri/?p=auto', tag='Bước 7 · Tự động viết bài', wait=3500,
    marks=[['fn:tiles', 1]],
    say='Bốn ô trạng thái cho biết bài kế tiếp, số bài đã đăng hôm nay, nhà cung cấp trí tuệ nhân tạo đang dùng, và tình trạng kết nối Google Sít.',
    cap='Bốn ô trạng thái: bài kế tiếp, số bài hôm nay, nhà cung cấp AI, kết nối Google Sheet (1).')

# ---------------------------------------------------------------- 8. Google Sheet (slide mô phỏng)
SHEET = 'slides/sheet.html'
add(id='08a-sheet', kind='html', html=SHEET, tag='Bước 8 · Google Sheet điều khiển máy viết bài',
    marks=[['all:[data-col=A]', 1], ['all:[data-col=B]', 2], ['all:[data-col=C]', 3], ['all:[data-col=D]', 4]],
    say='Máy viết bài đọc yêu cầu từ Google Sít. Mỗi dòng là một bài. Cột A ghi từ khóa chính, số một. Cột B là Trạng thái, số hai. Cột C là chuyên mục, và cột D là dạng bài. Các cột còn lại là ý định, từ khóa phụ, ảnh, video và ghi chú góc viết.',
    cap='Mỗi dòng của tab "Từ khóa" là một bài: từ khóa chính (A), Trạng thái (B), Chuyên mục (C), Dạng bài (D).')
add(id='08b-sheet', kind='html', html=SHEET, tag='Bước 8 · Google Sheet điều khiển máy viết bài',
    marks=[['all:[data-col=K]', 1], ['all:[data-col=B]', 2]],
    say='Muốn bài được đăng, ghi ngày giờ vào cột K, số một, ví dụ hai không hai sáu, gạch, mười một, gạch, không một, chín giờ ba mươi. Chỉ ghi ngày thì hệ thống dùng giờ mặc định. Rồi đổi cột Trạng thái, số hai, sang Sẵn sàng. Chỉ dòng Sẵn sàng mới được đăng, đúng vào giờ đã ghi.',
    cap='Ghi ngày giờ vào cột K (vd 2026-11-01 09:30 hoặc 01/11/2026 9:30) (1), rồi đổi Trạng thái sang "Sẵn sàng" (2).')
add(id='08c-sheet', kind='html', html=SHEET, tag='Bước 8 · Google Sheet điều khiển máy viết bài',
    marks=[['all:.ideas', 1], ['all:[data-col=L],[data-col=M],[data-col=N]', 2]],
    say='Dòng Ý tưởng không bao giờ tự đăng. Muốn hoãn một bài, đổi Trạng thái thành Tạm dừng. Kết quả được ghi ngược lại vào các cột bên phải, số hai: mã bài, tiêu đề bài, link bài, và thông báo lỗi nếu có.',
    cap='"Ý tưởng" không tự đăng; "Tạm dừng" để hoãn (1). Hệ thống ghi kết quả vào các cột bên phải (2).')
add(id='08d-sheet', kind='html', html=SHEET, tag='Bước 8 · Google Sheet điều khiển máy viết bài',
    marks=[['all:[data-col=H]', 1]],
    say='Cột H nhận link ảnh hoặc link cả thư mục Google Drive, để chế độ Bất kỳ ai có đường liên kết. Để trống thì bài không có ảnh. Tiêu đề các mục trong bài được tự đánh số một, hai, ba. Bạn đặt tên tệp bắt đầu bằng số mục, ví dụ hai gạch ngang pin sạc nhanh, thì ảnh nằm đúng mục số hai, và nội dung mục đó viết theo tên tệp. Ảnh bìa đặt tên bắt đầu bằng số không hoặc chữ bìa. Ảnh không đặt số thì A I tự chọn mục.',
    cap='Cột H: link ảnh hoặc thư mục Drive (1). Tiêu đề mục tự đánh số 1., 2., 3.… Tên tệp "2-pin-sac-nhanh.jpg" → ảnh nằm ở mục 2; ảnh bìa đặt tên "0-…" hoặc "bia-…"; ảnh không số thì AI tự chọn mục.')
add(id='08e-sheet', kind='html', html=SHEET, tag='Bước 8 · Google Sheet điều khiển máy viết bài',
    marks=[['all:[data-col=I]', 1]],
    say='Cột I nhận link video. Muốn đặt video vào một mục, ghi thêm dấu gạch đứng và tên mục.',
    cap='Cột I: link video, thêm "| tên mục" để đặt đúng chỗ (1).')

# ---------------------------------------------------------------- 9. lịch đăng + cài đặt
add(id='09-schedule', kind='page', path='/quan-tri/?p=auto', tag='Bước 9 · Lịch đăng và cài đặt', wait=3500,
    js="[...document.querySelectorAll('h2')].find(h => h.textContent.includes('Lịch đăng')).scrollIntoView({block:'start'}); window.scrollBy(0,-70)",
    marks=[['text:Mở Google Sheet', 1, 'r'], ['table', 2]],
    say='Mục Lịch đăng lấy trực tiếp từ Google Sít: từ khóa, giờ đăng, trạng thái và kết quả của từng dòng. Số một là nút mở nhanh Google Sít. Số hai là bảng lịch, bạn theo dõi bài nào đã đăng, bài nào đang chờ ở đây.',
    cap='Lịch đăng lấy từ Google Sheet. Nút Mở Google Sheet (1), bảng theo dõi từng dòng (2).')
add(id='10-settings', kind='page', path='/quan-tri/?p=auto', tag='Bước 9 · Lịch đăng và cài đặt', wait=3500,
    js="[...document.querySelectorAll('h2')].find(h => h.textContent.trim() === 'Cài đặt').scrollIntoView({block:'start'}); window.scrollBy(0,-60)",
    marks=[['fn:sheetUrl', 1], ['input[name=default_hour]', 2], ['fn:fieldset', 3]],
    say='Mục Cài đặt có link Google Sít, số một. Giờ đăng mặc định, số hai, dùng khi Sít chỉ ghi ngày. Bên dưới là nhà cung cấp trí tuệ nhân tạo: chọn một nơi, điền model và dán khóa A P I, số ba. Khóa được cất kín và không hiện lại trên trang.',
    cap='Link Google Sheet (1), giờ đăng mặc định (2), chọn nhà cung cấp AI, model và khóa API (3).')

# ---------------------------------------------------------------- 10. kiểm tra nội dung
add(id='11-audit', kind='page', path='/quan-tri/?p=audit', tag='Bước 10 · Kiểm tra nội dung', wait=2500,
    marks=[['text:Tự gắn liên kết nội bộ', 1, 'r'], ['table th:nth-child(2)', 2], ['table th:nth-child(4)', 3]],
    say='Kiểm tra nội dung chấm điểm từng bài. Phía trên là tổng quan số bài, bài có vấn đề nặng và bài cần xem lại. Số một là nút Tự gắn liên kết nội bộ giữa các bài. Số hai là điểm S E O của từng bài, số ba là những vấn đề cần xem. Bấm Sửa để chỉnh bài.',
    cap='Tổng quan, nút Tự gắn liên kết nội bộ (1), điểm SEO từng bài (2), vấn đề cần xem (3). Bấm Sửa để chỉnh.')

# ---------------------------------------------------------------- 11. review
add(id='12-review', kind='page', path='/quan-tri/?p=review', tag='Bước 11 · Review và học',
    marks=[['fn:reviewCard', 1]],
    say='Ở Review và học, bài do trí tuệ nhân tạo viết sẽ chờ nhân viên xem. Mở bài, đọc kỹ rồi chọn Không cần sửa, hoặc sửa bài xong bấm Đã sửa và chọn lý do. Hệ thống so với bản ban đầu để rút kinh nghiệm cho các bài sau.',
    cap='Bài AI viết chờ nhân viên xem: "Không cần sửa" hoặc sửa rồi bấm "Đã sửa" + lý do để hệ thống học.')

# ---------------------------------------------------------------- 12. người dùng / mã đo lường / tài khoản
add(id='13-users', kind='page', path='/quan-tri/?p=users', tag='Bước 12 · Người dùng và tài khoản',
    marks=[['fn:rolesCard', 1], ['fn:addUserCard', 2]],
    say='Ở mục Người dùng có ba vai trò. Quản trị có toàn quyền. Biên tập S E O sửa bài, ảnh và review nhưng không đổi cài đặt. Chỉ xem thì chỉ được xem. Để thêm người, điền email, tên, vai trò và mật khẩu ít nhất mười ký tự.',
    cap='Ba vai trò: Quản trị, Biên tập SEO, Chỉ xem (1). Thêm người: email, tên, vai trò, mật khẩu ≥ 10 ký tự (2).')
add(id='14-tracking', kind='page', path='/quan-tri/?p=tracking', tag='Bước 12 · Người dùng và tài khoản',
    marks=[['fn:gtmField', 1]],
    say='Mã đo lường cho phép dán mã Google Tag Manager, G A bốn, Google Ads hoặc Mê ta Pích xồ mà không cần sửa code. Các mã chỉ chạy sau khi khách bấm Đồng ý ở hộp cookie. Mỗi công cụ chỉ nên cài ở một nơi để không bị đếm đôi.',
    cap='Dán mã GTM / GA4 / Google Ads / Meta Pixel (1). Mã chỉ chạy sau khi khách bấm Đồng ý cookie.')
add(id='15-account', kind='page', path='/quan-tri/?p=account', tag='Bước 12 · Người dùng và tài khoản',
    marks=[['#pw', 1]],
    say='Mục Tài khoản để đổi mật khẩu của bạn. Hãy đổi mật khẩu định kỳ và không dùng chung tài khoản.',
    cap='Đổi mật khẩu ở mục Tài khoản (1). Nên đổi định kỳ, không dùng chung.')

# ---------------------------------------------------------------- 13. kết quả trên website
add(id='16-blog', kind='page', path='/blog/', nologin=True, tag='Bước 13 · Kết quả trên website',
    say='Bài đăng xong sẽ hiện ở trang Blog của website, có chuyên mục và phân trang. Mỗi lần bạn đăng hoặc sửa bài, hệ thống tự cập nhật danh sách bài, sơ đồ trang web và nguồn R S S.',
    cap='Bài đăng xong hiện ở trang Blog. Danh sách, sơ đồ trang web (sitemap) và RSS tự cập nhật.')
add(id='17a-post', kind='page', path='/blog/chi-phi-van-hanh-vf-wild/', nologin=True, tag='Bước 13 · Kết quả trên website',
    marks=[['.post__meta', 1], ['.post__by', 2]],
    say='Trên trang bài viết, số một là ngày đăng và ngày Cập nhật. Ngày cập nhật chỉ tự đổi khi bạn sửa nội dung thật. Số hai là tác giả.',
    cap='Ngày đăng và ngày Cập nhật (1) – chỉ đổi khi bạn sửa nội dung thật. Tác giả (2).')
add(id='17b-post', kind='page', path='/blog/chi-phi-van-hanh-vf-wild/', nologin=True, tag='Bước 13 · Kết quả trên website', scroll='.post__toc', scrollBy=-110,
    marks=[['.post__toc', 1]],
    say='Số một là mục lục, tự tạo từ các tiêu đề trong bài. Bài còn tự có bảng, video và mục câu hỏi thường gặp nếu bạn đã viết.',
    cap='Mục lục tự tạo từ các tiêu đề (1), cùng bảng, video và câu hỏi thường gặp nếu bài có.')

# ---------------------------------------------------------------- kết
add(id='99-outro', kind='html', html='slides/outro.html', tag='',
    say='Tóm lại. Muốn viết tay thì dùng mục Bài viết. Muốn tự động thì điền Google Sít và đổi trạng thái sang Sẵn sàng. Luôn xem điểm ở Kiểm tra nội dung. Cảm ơn bạn đã xem.',
    cap='Viết tay: Bài viết · Tự động: Google Sheet + "Sẵn sàng" · Kiểm tra: Kiểm tra nội dung. Cảm ơn bạn đã xem!')

if __name__ == '__main__':
    json.dump({'base': BASE, 'scenes': S}, open(os.path.join(HERE, 'scenes.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    print(len(S), 'cảnh')
