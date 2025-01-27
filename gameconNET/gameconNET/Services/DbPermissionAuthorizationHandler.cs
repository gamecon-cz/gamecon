using gameconNET.Models.DB;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Authorization.Infrastructure;
using Microsoft.EntityFrameworkCore;

namespace gameconNET.Services;

public class DbPermissionAuthorizationHandler : AuthorizationHandler<RolesAuthorizationRequirement>
{
    private readonly GameConContext _dbContext;


    public DbPermissionAuthorizationHandler(GameConContext identityTenantDbContext)
    {
        _dbContext = identityTenantDbContext;
    }

    protected override async Task HandleRequirementAsync(AuthorizationHandlerContext context,
        RolesAuthorizationRequirement requirement)
    {
        if (context.User == null || !context.User.Identity.IsAuthenticated)
        {
            context.Fail();
            return;
        }

        var found = false;
        if (requirement.AllowedRoles == null ||
            requirement.AllowedRoles.Any() == false)
        {
            // it means any logged in user is allowed to access the resource
            found = true;
        }
        else
        {
            var userId = context.User.Identity.Name;
            var permissions = requirement.AllowedRoles;
            var permIds = await _dbContext.Permissions
                .Where(p => permissions.Contains(p.Name) || permissions.Contains(p.Id.ToString()))
                .Select(p => p.Id)
                .ToListAsync();

            found = await _dbContext.Users.Include(u => u.Roles).ThenInclude(r => r.Permissions)
                .AnyAsync(u => u.Id.ToString() == userId && u.Roles.Any(r => r.Permissions.Any(p => permIds.Contains(p.Id))));
        }

        if (found)
        {
            context.Succeed(requirement);
        }
        else
        {
            context.Fail();
        }
    }
}
