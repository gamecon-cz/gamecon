using Google.Apis.Admin.Directory.directory_v1;
using Google.Apis.Admin.Directory.directory_v1.Data;
using Google.Apis.Auth.OAuth2;
using Google.Apis.Services;

namespace gameconNET.Services;

public class GoogleDriveService
{
    private DirectoryService _directoryService;

    public GoogleDriveService()
    {
        using (var stream =
               new FileStream("googleAdminServiceAccountKey.json", FileMode.Open, FileAccess.Read))
        {
            var credentials = GoogleCredential.FromStream(stream);
            if (credentials.IsCreateScopedRequired)
            {
                credentials = credentials.CreateScoped(new string[] { DirectoryService.Scope.AdminDirectoryGroup }).CreateWithUser("adrijaned@gamecon.cz");
            }


            _directoryService = new DirectoryService(new BaseClientService.Initializer()
            {
                HttpClientInitializer = credentials,
                ApplicationName = "JindraDBG",
            });
        }
    }

    public List<string> GetMembersOfGroup(string groupId)
    {
        string? nextPage = null;
        List<string> result = new List<string>();
        do
        {
            var listRequest = _directoryService.Members.List(groupId);
            listRequest.MaxResults = 5;
            listRequest.PageToken = nextPage;
            Members members = listRequest.Execute();
            nextPage = members.NextPageToken;
            foreach (var member in members.MembersValue)
            {
                result.Add(member.Email);
            }

        } while (nextPage != null);
        return result;
    }

    public void AddMemberToGroup(string groupId, string email)
    {
        var m = new Member
        {
            Email = email,
            Kind = "admin#directory#member",
            Role = "MEMBER",
            Type = "USER"
        };
        var insertRequest = _directoryService.Members.Insert(m, groupId);
        var member = insertRequest.Execute();
    }

    public void RemoveMemberFromGroup(string groupId, string email)
    {
        var deleteRequest = _directoryService.Members.Delete(groupId, email);
        var member = deleteRequest.Execute();
    }
}
