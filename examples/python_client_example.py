#!/usr/bin/env python3
"""
مثال استفاده از API ماموریت‌ها با Python

این اسکریپت نشان می‌دهد چگونه می‌توان از Metadata API استفاده کرد
و سپس ماموریت‌ها را ایجاد و مدیریت کرد.
"""

import requests
import json
from typing import Dict, Optional, List

class MissionAPIClient:
    """کلاینت Python برای API ماموریت‌ها"""
    
    def __init__(self, base_url: str, token: Optional[str] = None):
        """
        Initialize API client
        
        Args:
            base_url: Base URL of the API (e.g., "https://your-domain.com/api/api/v1")
            token: API token (optional, can be set later)
        """
        self.base_url = base_url.rstrip('/')
        self.token = token
        self.session = requests.Session()
        if token:
            self.set_token(token)
    
    def set_token(self, token: str):
        """Set API token"""
        self.token = token
        self.session.headers.update({
            'Authorization': f'Bearer {token}',
            'X-API-Token': token,
            'Content-Type': 'application/json'
        })
    
    def get_metadata(self) -> Dict:
        """
        دریافت metadata (Tenants, AI/LLMs, etc.)
        
        Returns:
            Dict containing tenants, ai_llms, content_types, etc.
        """
        url = f"{self.base_url}/metadata"
        response = self.session.get(url)
        response.raise_for_status()
        return response.json()
    
    def test_token(self) -> Dict:
        """
        تست توکن API
        
        Returns:
            Dict containing token information
        """
        url = f"{self.base_url}/test-token"
        response = self.session.get(url)
        response.raise_for_status()
        return response.json()
    
    def create_mission(
        self,
        title: str,
        prompt_content: str,
        tenant_id: Optional[int] = None,
        ai_id: Optional[int] = None,
        points: int = 0,
        **kwargs
    ) -> Dict:
        """
        ایجاد ماموریت جدید
        
        Args:
            title: عنوان ماموریت
            prompt_content: محتوای پرامپت
            tenant_id: شناسه Tenant (برای Super Admin اجباری)
            ai_id: شناسه AI پیشنهادی
            points: امتیاز ماموریت
            **kwargs: سایر فیلدها (description, content_title, etc.)
        
        Returns:
            Dict containing created mission
        """
        url = f"{self.base_url}/missions"
        data = {
            'title': title,
            'prompt_content': prompt_content,
            'points': points,
            **kwargs
        }
        
        if tenant_id:
            data['tenant_id'] = tenant_id
        if ai_id:
            data['ai_id'] = ai_id
        
        response = self.session.post(url, json=data)
        response.raise_for_status()
        return response.json()
    
    def list_missions(self, page: int = 1, per_page: int = 15) -> Dict:
        """
        دریافت لیست ماموریت‌ها
        
        Args:
            page: شماره صفحه
            per_page: تعداد در هر صفحه
        
        Returns:
            Dict containing paginated missions
        """
        url = f"{self.base_url}/missions"
        params = {'page': page, 'per_page': per_page}
        response = self.session.get(url, params=params)
        response.raise_for_status()
        return response.json()
    
    def get_mission(self, mission_id: int) -> Dict:
        """
        دریافت یک ماموریت
        
        Args:
            mission_id: شناسه ماموریت
        
        Returns:
            Dict containing mission details
        """
        url = f"{self.base_url}/missions/{mission_id}"
        response = self.session.get(url)
        response.raise_for_status()
        return response.json()
    
    def add_content(
        self,
        mission_id: int,
        title: str,
        content_url: Optional[str] = None,
        content_type: str = 'text',
        **kwargs
    ) -> Dict:
        """
        اضافه کردن محتوای آموزشی
        
        Args:
            mission_id: شناسه ماموریت
            title: عنوان محتوا
            content_url: لینک محتوا
            content_type: نوع محتوا (text, video, image, audio, pdf)
            **kwargs: سایر فیلدها
        
        Returns:
            Dict containing added content
        """
        url = f"{self.base_url}/missions/{mission_id}/content"
        data = {
            'title': title,
            'content_type': content_type,
            **kwargs
        }
        if content_url:
            data['content_url'] = content_url
        
        response = self.session.post(url, json=data)
        response.raise_for_status()
        return response.json()
    
    def assign_mission(self, mission_id: int, personnel_id: int) -> Dict:
        """
        اختصاص ماموریت به پرسنل
        
        Args:
            mission_id: شناسه ماموریت
            personnel_id: شناسه پرسنل
        
        Returns:
            Dict containing assignment result
        """
        url = f"{self.base_url}/missions/{mission_id}/assign"
        data = {'personnel_id': personnel_id}
        response = self.session.post(url, json=data)
        response.raise_for_status()
        return response.json()
    
    def submit_result(
        self,
        mission_id: int,
        result_link: str,
        personnel_id: int,
        selected_ai_id: Optional[int] = None
    ) -> Dict:
        """
        ارسال لینک نتیجه
        
        Args:
            mission_id: شناسه ماموریت
            result_link: لینک نتیجه
            personnel_id: شناسه پرسنل
            selected_ai_id: شناسه AI انتخابی
        
        Returns:
            Dict containing submission result
        """
        url = f"{self.base_url}/missions/{mission_id}/submit"
        data = {
            'result_link': result_link,
            'personnel_id': personnel_id
        }
        if selected_ai_id:
            data['selected_ai_id'] = selected_ai_id
        
        response = self.session.post(url, json=data)
        response.raise_for_status()
        return response.json()


def main():
    """مثال استفاده از کلاینت"""
    
    # تنظیمات
    BASE_URL = "https://your-domain.com/api/api/v1"
    TOKEN = "YOUR_TOKEN_HERE"
    
    # ایجاد کلاینت
    client = MissionAPIClient(BASE_URL, TOKEN)
    
    print("=" * 60)
    print("🚀 شروع استفاده از API ماموریت‌ها")
    print("=" * 60)
    
    # 1. دریافت Metadata
    print("\n1️⃣ دریافت Metadata...")
    try:
        metadata = client.get_metadata()
        print(f"✅ Metadata دریافت شد")
        print(f"   - تعداد Tenants: {len(metadata['data']['tenants'])}")
        print(f"   - تعداد AI/LLMs: {len(metadata['data']['ai_llms'])}")
        
        # استخراج اطلاعات
        tenants = metadata['data']['tenants']
        ai_llms = metadata['data']['ai_llms']
        
        if tenants:
            tenant_id = tenants[0]['id']
            print(f"   - Tenant ID انتخابی: {tenant_id}")
        
        if ai_llms:
            ai_id = ai_llms[0]['id']
            print(f"   - AI ID انتخابی: {ai_id} ({ai_llms[0]['name']})")
        
    except Exception as e:
        print(f"❌ خطا در دریافت Metadata: {e}")
        return
    
    # 2. تست توکن
    print("\n2️⃣ تست توکن...")
    try:
        token_info = client.test_token()
        print(f"✅ توکن معتبر است")
        print(f"   - نوع: {token_info['data']['type']}")
        print(f"   - Tenant ID: {token_info['data']['tenant_id']}")
    except Exception as e:
        print(f"❌ خطا در تست توکن: {e}")
        return
    
    # 3. ایجاد ماموریت
    print("\n3️⃣ ایجاد ماموریت...")
    try:
        mission = client.create_mission(
            title="ماموریت تست از Python",
            prompt_content="این پرامپت برای کپی کردن است",
            tenant_id=tenant_id if 'tenant_id' in locals() else None,
            ai_id=ai_id if 'ai_id' in locals() else None,
            points=10,
            description="توضیحات ماموریت تست"
        )
        print(f"✅ ماموریت ایجاد شد")
        print(f"   - ID: {mission['data']['id']}")
        print(f"   - عنوان: {mission['data']['title']}")
        mission_id = mission['data']['id']
    except Exception as e:
        print(f"❌ خطا در ایجاد ماموریت: {e}")
        return
    
    # 4. اضافه کردن محتوا
    print("\n4️⃣ اضافه کردن محتوا...")
    try:
        content = client.add_content(
            mission_id=mission_id,
            title="ویدیو آموزشی",
            content_url="https://example.com/video.mp4",
            content_type="video",
            description="توضیحات ویدیو"
        )
        print(f"✅ محتوا اضافه شد")
    except Exception as e:
        print(f"❌ خطا در اضافه کردن محتوا: {e}")
    
    # 5. دریافت لیست ماموریت‌ها
    print("\n5️⃣ دریافت لیست ماموریت‌ها...")
    try:
        missions = client.list_missions(page=1, per_page=10)
        print(f"✅ {len(missions['data']['data'])} ماموریت دریافت شد")
    except Exception as e:
        print(f"❌ خطا در دریافت لیست: {e}")
    
    print("\n" + "=" * 60)
    print("✅ تمام عملیات با موفقیت انجام شد!")
    print("=" * 60)


if __name__ == "__main__":
    main()

